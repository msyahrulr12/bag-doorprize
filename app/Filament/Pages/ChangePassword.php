<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Hash;
use Filament\Facades\Filament;
use BackedEnum;

class ChangePassword extends Page
{
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-key';

    protected string $view = 'filament.pages.change-password';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public function mount(): void
    {
        $user = Filament::auth()->user();
        if (!$user->must_change_password) {
            $this->redirect(Filament::getPanel()->getPath());
            return;
        }
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('password')
                    ->password()
                    ->required()
                    ->rule('min:8')
                    ->confirmed(),
                TextInput::make('password_confirmation')
                    ->password()
                    ->required(),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('updatePassword')
                ->label('Update Password')
                ->submit('updatePassword'),
        ];
    }

    public function updatePassword(): void
    {
        $data = $this->form->getState();
        $user = Filament::auth()->user();

        $user->update([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ]);

        Notification::make()
            ->title('Password updated successfully!')
            ->success()
            ->send();

        $this->redirect(Filament::getPanel()->getPath());
    }
}
