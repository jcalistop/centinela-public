<?php

namespace App\View\Components\Botones;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Http\Controllers\Traits\BreadRelationshipParser;

class DeleteButtonComponent extends Component
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
        public int $id,
        public string $etiqueta = 'Eliminar',
        public ?array $documentHubContext = null,
    ) {}

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        $dataType = Voyager::model('DataType')->where('name', '=', $this->table)->first();

        $documentHubContext = $this->documentHubContext;

        return view('components.botones.delete-button-component', compact('dataType', 'documentHubContext'));
    }
}
