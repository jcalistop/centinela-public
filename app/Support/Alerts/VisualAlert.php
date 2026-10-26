<?php

namespace App\Support\Alerts;

/**
 * @param  list<string>|null  $roles  null o [] = visible para todos los roles; si no vacío, filtrar con hasRole
 */
final readonly class VisualAlert
{
    public function __construct(
        public string $id,
        public VisualAlertModule $module,
        public VisualAlertSeverity $severity,
        public VisualAlertPlacement $placement,
        public string $title,
        public string $anchorKey,
        public int $priority = 50,
        public ?string $detail = null,
        public ?string $titleAttr = null,
        public ?string $url = null,
        public ?array $roles = null,
    ) {}

    public function visibleForRoles(string $roleName): bool
    {
        if ($this->roles === null || $this->roles === []) {
            return true;
        }

        return in_array($roleName, $this->roles, true);
    }

    public function groupKey(): string
    {
        return $this->anchorKey.'|'.$this->placement->value;
    }

    public function tooltipText(): string
    {
        if ($this->titleAttr !== null && $this->titleAttr !== '') {
            return $this->titleAttr;
        }

        if ($this->detail !== null && $this->detail !== '') {
            return $this->title.' — '.$this->detail;
        }

        return $this->title;
    }
}
