<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailLogResource\Pages;
use App\Models\EmailLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class EmailLogResource extends Resource
{
    protected static ?string $model = EmailLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static UnitEnum|string|null $navigationGroup = 'Gerenciamento';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Logs de Emails';

    protected static ?string $modelLabel = 'Log de Email';

    protected static ?string $pluralModelLabel = 'Logs de Emails';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações do Destinatário')
                    ->schema([
                        TextInput::make('recipient_email')
                            ->label('Email')
                            ->disabled(),

                        TextInput::make('recipient_name')
                            ->label('Nome')
                            ->disabled(),

                        Placeholder::make('notifiable')
                            ->label('Usuário Relacionado')
                            ->content(function (EmailLog $record): string {
                                if (!$record->notifiable) {
                                    return 'Nenhum';
                                }

                                return $record->notifiable_type.' #'.$record->notifiable_id;
                            }),
                    ])
                    ->columns(3),

                Section::make('Detalhes do Email')
                    ->schema([
                        TextInput::make('subject')
                            ->label('Assunto')
                            ->disabled()
                            ->columnSpanFull(),

                        TextInput::make('notification_type')
                            ->label('Tipo de Notificação')
                            ->disabled()
                            ->formatStateUsing(fn (string $state): string => class_basename($state)),

                        TextInput::make('status')
                            ->label('Status')
                            ->disabled()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'sent' => 'Enviado',
                                'failed' => 'Falhado',
                                'queued' => 'Na Fila',
                                default => ucfirst($state),
                            }),

                        TextInput::make('attempts')
                            ->label('Tentativas')
                            ->disabled(),
                    ])
                    ->columns(3),

                Section::make('Envio')
                    ->schema([
                        Placeholder::make('created_at')
                            ->label('Criado em')
                            ->content(fn (EmailLog $record): string => $record->created_at->format('d/m/Y H:i:s')),

                        Placeholder::make('sent_at')
                            ->label('Enviado em')
                            ->content(function (EmailLog $record): string {
                                if (!$record->sent_at) {
                                    return 'Não enviado';
                                }

                                return $record->sent_at->format('d/m/Y H:i:s');
                            }),

                        Placeholder::make('failed_at')
                            ->label('Falhou em')
                            ->content(function (EmailLog $record): string {
                                if (!$record->failed_at) {
                                    return 'N/A';
                                }

                                return $record->failed_at->format('d/m/Y H:i:s');
                            }),
                    ])
                    ->columns(3),

                Section::make('Erro')
                    ->schema([
                        Textarea::make('error_message')
                            ->label('Mensagem de Erro')
                            ->disabled()
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->hidden(fn (EmailLog $record): bool => $record->status !== 'failed'),

                Section::make('Metadata')
                    ->schema([
                        KeyValue::make('metadata')
                            ->label('Dados Adicionais')
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->hidden(fn (EmailLog $record): bool => empty($record->metadata)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('recipient_email')
                    ->label('Destinatário')
                    ->searchable()
                    ->sortable()
                    ->description(fn (EmailLog $record): ?string => $record->recipient_name),

                TextColumn::make('subject')
                    ->label('Assunto')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();

                        if (strlen($state) <= 50) {
                            return null;
                        }

                        return $state;
                    }),

                TextColumn::make('notification_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        'queued' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'sent' => 'Enviado',
                        'failed' => 'Falhado',
                        'queued' => 'Na Fila',
                        default => ucfirst($state),
                    })
                    ->sortable(),

                TextColumn::make('attempts')
                    ->label('Tentativas')
                    ->sortable()
                    ->toggleable()
                    ->hidden(fn (): bool => true),

                TextColumn::make('sent_at')
                    ->label('Enviado em')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'sent' => 'Enviado',
                        'failed' => 'Falhado',
                        'queued' => 'Na Fila',
                    ]),

                SelectFilter::make('notification_type')
                    ->label('Tipo de Notificação')
                    ->options(function () {
                        return EmailLog::query()
                            ->distinct()
                            ->pluck('notification_type', 'notification_type')
                            ->mapWithKeys(fn ($type) => [$type => class_basename($type)])
                            ->toArray();
                    })
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordAction(ViewAction::class)
            ->recordActions([
                ViewAction::make(),
            ])
            ->paginated([10, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailLogs::route('/'),
            'view' => Pages\ViewEmailLog::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
