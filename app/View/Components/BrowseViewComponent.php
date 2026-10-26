<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Http\Controllers\Traits\BreadRelationshipParser;

class BrowseViewComponent extends Component
{
    /**
     * Create a new component instance.
     */
    use BreadRelationshipParser;

    /**
     * @param  array{hub?: string|null, metas_route?: string|null, indicator_type_id?: int|null}|null  $documentHubContext
     */
    public function __construct(
        public string $table,
        public $scopeWhere = null,
        public $customScope = null,
        public bool $showButtons = true,
        public bool $download = true,
        public ?array $documentHubContext = null,
    ) {
        //
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        $viewType = 'browse';
        $dataType = Voyager::model('DataType')->where('name', '=', $this->table)->first();
        $model = app($dataType->model_name);
        $query = $model::select($dataType->name.'.*');
        if ($dataType->scope && $dataType->scope != '' && method_exists($model, 'scope'.ucfirst($dataType->scope))) {
            $query->{$dataType->scope}();
        }
        if (! is_null($this->customScope) && $this->customScope != '' && method_exists($model, 'scope'.ucfirst($this->customScope))) {
            $query->{$this->customScope}();
        }
        if (! is_null($this->scopeWhere)) {
            $query->where($this->scopeWhere);
        }
        $dataTypeContent = $query->get();
        $this->removeRelationshipField($dataType, 'browse');
        $actions = [];
        if (! empty($dataTypeContent->first())) {
            foreach (Voyager::actions() as $action) {
                $action = new $action($dataType, $dataTypeContent->first());

                if ($action->shouldActionDisplayOnDataType() && $this->showButtons) {
                    $actions[] = $action;
                }
            }
        }

        $documentHubContext = $this->documentHubContext;

        return view('components.browse-view-component', compact('dataType', 'dataTypeContent', 'actions', 'documentHubContext'));
    }
}
