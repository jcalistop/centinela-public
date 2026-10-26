<?php

namespace App\View\Components;

use App\Helpers\ModuleAccess;
use App\Models\IndicatorType;
use App\Models\User;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Component;
use TCG\Voyager\Facades\Voyager;

class MenuComponent extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public string $table,
        public string $itemField,
        public $scope = null
    ) {}

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        $dataType = Voyager::model('DataType')->where('name', '=', $this->table)->first();

        if ($dataType === null) {
            return view('components.menu-component', [
                'dataTypeContent' => collect(),
            ]);
        }

        $model = app($dataType->model_name);
        $query = $model::select($dataType->name.'.*');

        if ($dataType->scope && $dataType->scope != '' && method_exists($model, 'scope'.ucfirst($dataType->scope))) {
            $query->{$dataType->scope}();
        }

        if ($this->table !== 'indicator_types') {
            $query->where('estado', 1);
        }

        if (Schema::hasColumn($dataType->name, 'order')) {
            $dataTypeContent = $query->orderBy('order')->get();
        } else {
            $dataTypeContent = $query->get();
        }

        if ($this->table === 'indicator_types') {
            $user = Auth::user();
            if ($user instanceof User) {
                $dataTypeContent = $dataTypeContent->filter(
                    fn (IndicatorType $item) => ModuleAccess::canAccess((string) $item->route, null, $item)
                );
            }
        }

        return view('components.menu-component', compact('dataTypeContent'));
    }
}
