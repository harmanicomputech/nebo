<?php

namespace App\Notifications;

class AccountRolesChanged extends NeboNotification
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(public array $roles, public string $changedBy) {}

    public function title(): string
    {
        return 'Your access was updated';
    }

    public function body(): string
    {
        return $this->changedBy.' set your roles to: '.($this->roles ? implode(', ', $this->roles) : 'none').'.';
    }

    public function url(): ?string
    {
        return route('app.profile.edit');
    }

    public function icon(): string
    {
        return 'key-round';
    }
}
