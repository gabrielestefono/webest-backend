<?php

namespace App\Filament\Pages\Auth;

use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BaseLogin
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getAuthenticateFormAction(),
        ];
    }

    protected function getRegisterFormAction(): Action
    {
        return Action::make('register')
            ->label(__('filament-panels::auth/pages/login.actions.register.label'))
            ->url(filament()->getRegistrationUrl())
            ->color('info');
    }

    public function getSubheading(): string | Htmlable | null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return __('filament-panels::auth/pages/login.multi_factor.subheading');
        }

        return null;
    }

    public function getFormContentComponent(): Component
    {
        $footer = [
            Actions::make($this->getFormActions())
                ->alignment($this->getFormActionsAlignment())
                ->fullWidth($this->hasFullWidthFormActions())
                ->key('form-actions'),
        ];

        if (filament()->hasRegistration()) {
            $footer[] = Actions::make([$this->getRegisterFormAction()])
                ->alignment($this->getFormActionsAlignment())
                ->fullWidth(true)
                ->key('register-action');
        }

        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('authenticate')
            ->footer($footer)
            ->visible(fn (): bool => blank($this->userUndertakingMultiFactorAuthentication));
    }
}
