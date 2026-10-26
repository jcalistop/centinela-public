<?php

namespace App\Http\Controllers\Voyager;

use App\Helpers\Helper;
use App\Models\User;
use App\Models\Year;
use App\Services\Users\UserReferentIndicatorsService;
use App\Support\Voyager\CentinelaValidatesBreadUploads;
use App\Support\Voyager\VoyagerUserActorAuthorization;
use App\Support\Voyager\VoyagerUserBelongsToFkFromRequest;
use App\Support\Voyager\VoyagerUserBladeFieldsHydration;
use App\Support\Voyager\VoyagerUserControlGestionCenHieDependency;
use App\Support\Voyager\VoyagerUserCredentialsMailer;
use App\Support\Voyager\VoyagerUserEmailValidation;
use App\Support\Voyager\VoyagerUserNameValidation;
use App\Support\Voyager\VoyagerUserPasswordValidation;
use App\Support\Voyager\VoyagerUserRoleValidation;
use App\Support\Voyager\VoyagerUserSelfProfileSessionRefresh;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Http\Controllers\VoyagerUserController;

class CentinelaVoyagerUserController extends VoyagerUserController
{
    use CentinelaValidatesBreadUploads;

    /**
     * Guardia SDD-175: el invariante de privacidad vive en data_types.details.scope.
     * Si alguien lo borra desde el editor BREAD, no listar usuarios sin filtro.
     *
     * {@inheritdoc}
     */
    public function index(Request $request)
    {
        if ($this->getSlug($request) === 'users') {
            $this->assertUsersBreadScopeConfigured();
        }

        return parent::index($request);
    }

    /**
     * @param  int|Model|string  $id
     *
     * {@inheritdoc}
     */
    public function show(Request $request, $id)
    {
        $view = parent::show($request, $id);

        if (! $view instanceof View) {
            return $view;
        }

        $user = $view->getData()['dataTypeContent'] ?? null;
        if (! $user instanceof User) {
            return $view;
        }

        $year = Helper::showCurrentYear();
        $referentIndicators = app(UserReferentIndicatorsService::class)->forUser(
            $user,
            $year instanceof Year ? $year : null,
        );

        return $view->with([
            'referentIndicators' => $referentIndicators,
            'referentIndicatorsYear' => $year instanceof Year ? $year->ano : null,
        ]);
    }

    /**
     * Voyager (clase base) declara `insertUpdateData` sin tipos en la firma PHP; el PHPDoc satisface Intelephense sin ampliar tipos respecto al padre (evita avisos de incompatibilidad).
     *
     * Voyager solo persiste columnas con fila en el BREAD; el Blade publicado puede definir otras adicionales.
     *
     * @param  Request  $request
     * @param  string  $slug
     * @param  mixed  $rows
     * @param  mixed  $data
     * @return mixed
     *
     * {@inheritdoc}
     */
    public function insertUpdateData($request, $slug, $rows, $data)
    {
        if ($slug === 'users' && $data instanceof User) {
            $this->guardUsersBreadRoleOnRequest($request, $data);
            VoyagerUserControlGestionCenHieDependency::applyToRequestAndModel($data, $request);
            VoyagerUserBladeFieldsHydration::hydrateFromPublishedBlade($data, $request, includePassword: false);
        }

        $data = parent::insertUpdateData($request, $slug, $rows, $data);

        if ($slug === 'users' && $data instanceof User) {
            VoyagerUserBladeFieldsHydration::persistIfOutOfSync($data, $request);
        }

        return $data;
    }

    /**
     * PATCH: tras el guardado Voyager, aplicar FK provenientes de inputs de relación belongsTo.
     *
     * @param  int|Model|string  $id
     *
     * {@inheritdoc}
     */
    public function update(Request $request, $id)
    {
        $plainPasswordForCredentials = null;
        $isSelfProfileUpdate = false;
        $selfProfilePasswordChanged = false;

        if ($this->isUsersBread($request)) {
            $resolvedId = $id instanceof Model ? $id->{$id->getKeyName()} : $id;
            $userId = (int) $resolvedId;
            $auth = auth()->user();
            $target = User::query()->find($userId);
            if ($auth instanceof User && $target instanceof User && (int) $auth->getKey() === (int) $target->getKey()) {
                $isSelfProfileUpdate = true;
                $selfProfilePasswordChanged = $request->filled('password');
            }

            VoyagerUserPasswordValidation::normalizeRequestForUpdate($request);
            VoyagerUserNameValidation::validate($request);
            VoyagerUserEmailValidation::validate($request, $userId);
            VoyagerUserPasswordValidation::validateForOptionalPasswordOnUpdate($request);
            if ($this->mustValidateUserRoleSelection($userId)) {
                VoyagerUserRoleValidation::validate($request);
            }

            $auth = auth()->user();
            $target = User::query()->find($userId);
            if ($auth instanceof User && $target instanceof User && (int) $auth->getKey() !== (int) $target->getKey()) {
                VoyagerUserActorAuthorization::assertCanManageUser($auth, $target);
                VoyagerUserControlGestionCenHieDependency::applyToRequestAndModel($target, $request);
            } elseif ($target instanceof User) {
                VoyagerUserControlGestionCenHieDependency::applyToRequestAndModel($target, $request);
            }
            if ($auth instanceof User && $target instanceof User && $this->shouldSendCredentialsEmail($request, $auth, $target, false)) {
                VoyagerUserPasswordValidation::validatePasswordRequiredWhenSendingCredentials($request);
                $plainPasswordForCredentials = (string) $request->input('password');
            }
        }

        $response = parent::update($request, $id);

        $id = $id instanceof Model ? $id->{$id->getKeyName()} : $id;
        $userId = (int) $id;
        $user = User::query()->find($userId);
        if ($user instanceof User) {
            VoyagerUserBelongsToFkFromRequest::sync($user, $request);
            if ($response instanceof RedirectResponse && $this->isUsersBread($request)) {
                if ($isSelfProfileUpdate) {
                    $response = $this->redirectAfterSelfProfileUpdate(
                        $response,
                        $user,
                        $selfProfilePasswordChanged,
                    );
                } else {
                    $response = $this->redirectUsersToReadAfterSave($response, $user);
                }
                $response = $this->appendCredentialsEmailFlash(
                    $response,
                    $request,
                    $user,
                    $plainPasswordForCredentials,
                    isUpdate: true,
                );
            }
        }

        return $response;
    }

    /**
     * PATCH: igual que update al crear un usuario nuevo.
     *
     * {@inheritdoc}
     */
    public function store(Request $request)
    {
        if ($request->has('_tagging')) {
            return parent::store($request);
        }

        $plainPasswordForCredentials = null;

        if ($this->isUsersBread($request)) {
            VoyagerUserNameValidation::validate($request);
            VoyagerUserEmailValidation::validate($request);
            VoyagerUserPasswordValidation::validateForStore($request);
            if ($this->mustValidateUserRoleSelection(null)) {
                VoyagerUserRoleValidation::validate($request);
            } elseif (VoyagerUserActorAuthorization::resolveRoleFromRequest($request) !== null) {
                VoyagerUserRoleValidation::validateAssignableRole($request);
            }

            $auth = auth()->user();
            if ($auth instanceof User && $this->shouldSendCredentialsEmail($request, $auth, null, true)) {
                VoyagerUserPasswordValidation::validatePasswordRequiredWhenSendingCredentials($request);
                $plainPasswordForCredentials = (string) $request->input('password');
            }
        }

        VoyagerUserControlGestionCenHieDependency::applyToRequestAndModel(new User, $request);

        $emailBefore = (string) $request->input('email', '');

        $response = parent::store($request);

        if ($emailBefore !== '') {
            $user = User::query()->where('email', $emailBefore)->orderByDesc('id')->first();
            if ($user instanceof User) {
                VoyagerUserBelongsToFkFromRequest::sync($user, $request);
                if ($response instanceof RedirectResponse && $this->isUsersBread($request)) {
                    $response = $this->redirectUsersToReadAfterSave($response, $user);
                    $response = $this->appendCredentialsEmailFlash(
                        $response,
                        $request,
                        $user,
                        $plainPasswordForCredentials,
                        isUpdate: false,
                    );
                }
            }
        }

        return $response;
    }

    private function isUsersBread(Request $request): bool
    {
        return $this->getSlug($request) === 'users';
    }

    /**
     * SDD-175: exige que el BREAD users conserve details.scope = voyagerActorManageable.
     */
    private function assertUsersBreadScopeConfigured(): void
    {
        $dataType = Voyager::model('DataType')->where('slug', '=', 'users')->first();
        $scope = is_object($dataType) ? (string) ($dataType->scope ?? '') : '';

        if ($scope === 'voyagerActorManageable') {
            return;
        }

        Log::error('SDD-175: BREAD users sin scope voyagerActorManageable; browse bloqueado.', [
            'scope' => $scope === '' ? null : $scope,
            'data_type_id' => is_object($dataType) ? $dataType->getKey() : null,
        ]);

        abort(403, 'Configuración BREAD de usuarios incompleta: falta el scope de privacidad por Servicio de Salud.');
    }

    /**
     * Solo si aparece el bloque del rol (@can editRoles): validar selección obligatoria.
     * Si el usuario edita su propio registro, el padre de Voyager fija role_id después.
     *
     * @param  ?int  $editedUserId  null en alta de usuario.
     */
    private function mustValidateUserRoleSelection(?int $editedUserId): bool
    {
        $auth = auth()->user();
        if (! $auth instanceof User) {
            return false;
        }

        if ($editedUserId !== null && (int) $auth->getKey() === $editedUserId) {
            return false;
        }

        $subject = $editedUserId !== null
            ? User::query()->find($editedUserId)
            : new User;

        return $subject instanceof User && $auth->can('editRoles', $subject);
    }

    private function maySendCredentialsEmail(User $auth, ?User $target, bool $isCreate): bool
    {
        if ($isCreate) {
            return $auth->hasPermission('add_users');
        }

        if (! $target instanceof User) {
            return false;
        }

        return $auth->hasPermission('edit_users')
            && (int) $auth->getKey() !== (int) $target->getKey();
    }

    private function shouldSendCredentialsEmail(Request $request, User $auth, ?User $target, bool $isCreate): bool
    {
        return $request->boolean('send_credentials_email')
            && $this->maySendCredentialsEmail($auth, $target, $isCreate);
    }

    private function appendCredentialsEmailFlash(
        RedirectResponse $response,
        Request $request,
        User $user,
        ?string $plainPassword,
        bool $isUpdate,
    ): RedirectResponse {
        $auth = auth()->user();
        if (! $auth instanceof User || $plainPassword === null || $plainPassword === '') {
            return $response;
        }

        if (! $this->shouldSendCredentialsEmail($request, $auth, $isUpdate ? $user : null, ! $isUpdate)) {
            return $response;
        }

        $existingMessage = (string) $response->getSession()->get('message', '');

        if (VoyagerUserCredentialsMailer::send($user, $plainPassword, $isUpdate)) {
            return $response->with([
                'message' => trim($existingMessage.' Las credenciales se enviaron por correo al usuario.'),
                'alert-type' => $response->getSession()->get('alert-type', 'success'),
            ]);
        }

        return $response->with([
            'message' => trim($existingMessage.' El usuario se guardó, pero no fue posible enviar el correo con las credenciales.'),
            'alert-type' => 'warning',
        ]);
    }

    private function redirectUsersToReadAfterSave(RedirectResponse $response, User $savedUser): RedirectResponse
    {
        $response->setTargetUrl(route('voyager.users.show', ['id' => $savedUser->getKey()]));

        return $response;
    }

    private function redirectAfterSelfProfileUpdate(
        RedirectResponse $response,
        User $savedUser,
        bool $passwordChanged,
    ): RedirectResponse {
        if ($passwordChanged) {
            VoyagerUserSelfProfileSessionRefresh::refreshAfterPasswordChange($savedUser);
            $response->setTargetUrl(route('voyager.dashboard'));

            return $response;
        }

        $response->setTargetUrl(route('voyager.profile'));

        return $response;
    }

    private function guardUsersBreadRoleOnRequest(Request $request, User $target): void
    {
        $actor = VoyagerUserActorAuthorization::authenticatedActor();
        if (! $actor instanceof User) {
            return;
        }

        // En autoedición de perfil, Voyager (VoyagerUserController::update) re-inyecta el role_id propio en el request.
        // Ejecutar aquí el guard de asignación de rol lo removería (canAssignRole=false para no admin/control-gestión),
        // dejando role_id NULL: el usuario pierde browse_admin y queda en bucle de redirección (ERR_TOO_MANY_REDIRECTS).
        if ($target->exists && (int) $actor->getKey() === (int) $target->getKey()) {
            return;
        }

        if ($target->exists) {
            VoyagerUserActorAuthorization::assertCanManageUser($actor, $target);
        }

        $role = VoyagerUserActorAuthorization::resolveRoleFromRequest($request);
        if ($role === null) {
            return;
        }

        if (! VoyagerUserActorAuthorization::canAssignRole($actor, $role)) {
            $request->request->remove('role_id');
            $request->request->remove('user_belongsto_role_relationship');
        }
    }
}
