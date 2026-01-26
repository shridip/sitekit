<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServerResource\Pages;
use App\Filament\Resources\ServerResource\RelationManagers;
use App\Models\Server;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ServerResource extends Resource
{
    protected static ?string $model = Server::class;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationGroup = 'Infrastructure';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('team_id', Filament::getTenant()?->id)
            ->where('status', Server::STATUS_ACTIVE)
            ->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Server Details')
                    ->description('Basic information about your server. The IP address will be automatically detected when the agent is installed.')
                    ->icon('heroicon-o-server')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('My Production Server')
                            ->helperText('A friendly name to identify this server'),
                        Forms\Components\Select::make('provider')
                            ->options([
                                Server::PROVIDER_CUSTOM => 'Custom / Other',
                                Server::PROVIDER_DIGITALOCEAN => 'DigitalOcean',
                                Server::PROVIDER_LINODE => 'Linode',
                                Server::PROVIDER_VULTR => 'Vultr',
                                Server::PROVIDER_HETZNER => 'Hetzner',
                                Server::PROVIDER_AWS => 'AWS',
                            ])
                            ->default(Server::PROVIDER_CUSTOM)
                            ->required()
                            ->helperText('Select your cloud provider for better organization'),
                        Forms\Components\TextInput::make('ip_address')
                            ->label('IP Address')
                            ->placeholder('Detected automatically')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Populated automatically after provisioning'),
                        Forms\Components\TextInput::make('ssh_port')
                            ->label('SSH Port')
                            ->default('22')
                            ->required()
                            ->helperText('Default is 22. Change only if using a custom SSH port'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Server Status')
                    ->description('Current server health and system information reported by the agent.')
                    ->icon('heroicon-o-signal')
                    ->schema([
                        Forms\Components\Placeholder::make('status_display')
                            ->label('Status')
                            ->content(fn (?Server $record) => $record?->status ?? 'New'),
                        Forms\Components\Placeholder::make('last_heartbeat')
                            ->label('Last Heartbeat')
                            ->content(fn (?Server $record) => $record?->last_heartbeat_at?->diffForHumans() ?? 'Never'),
                        Forms\Components\Placeholder::make('os_info')
                            ->label('Operating System')
                            ->content(fn (?Server $record) => $record?->os_name
                                ? "{$record->os_name} {$record->os_version}"
                                : 'Pending detection'),
                        Forms\Components\Placeholder::make('specs')
                            ->label('Specs')
                            ->content(fn (?Server $record) => $record?->cpu_count
                                ? "{$record->cpu_count} CPU, {$record->memory_mb} MB RAM, {$record->disk_gb} GB Disk"
                                : 'Pending detection'),
                    ])
                    ->columns(2)
                    ->hiddenOn('create'),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Server Details')
                    ->description('Basic server information and current status.')
                    ->icon('heroicon-o-server')
                    ->schema([
                        Infolists\Components\TextEntry::make('name'),
                        Infolists\Components\TextEntry::make('ip_address')
                            ->label('IP Address')
                            ->copyable()
                            ->placeholder('Not connected'),
                        Infolists\Components\TextEntry::make('provider')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                Server::PROVIDER_DIGITALOCEAN => 'DigitalOcean',
                                Server::PROVIDER_LINODE => 'Linode',
                                Server::PROVIDER_VULTR => 'Vultr',
                                Server::PROVIDER_HETZNER => 'Hetzner',
                                Server::PROVIDER_AWS => 'AWS',
                                default => 'Custom',
                            }),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'provisioning' => 'info',
                                'active' => 'success',
                                'offline', 'failed' => 'danger',
                                default => 'gray',
                            }),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make('System Information')
                    ->description('Hardware specifications and connectivity status reported by the agent.')
                    ->icon('heroicon-o-cpu-chip')
                    ->schema([
                        Infolists\Components\TextEntry::make('os_name')
                            ->label('Operating System')
                            ->formatStateUsing(fn ($record) => $record->os_name
                                ? "{$record->os_name} {$record->os_version}"
                                : 'Pending detection'),
                        Infolists\Components\TextEntry::make('cpu_count')
                            ->label('CPU Cores')
                            ->suffix(' cores'),
                        Infolists\Components\TextEntry::make('memory_mb')
                            ->label('Memory')
                            ->formatStateUsing(fn ($state) => $state ? round($state / 1024, 1) . ' GB' : 'Pending'),
                        Infolists\Components\TextEntry::make('disk_gb')
                            ->label('Disk')
                            ->suffix(' GB'),
                        Infolists\Components\TextEntry::make('last_heartbeat_at')
                            ->label('Last Heartbeat')
                            ->since()
                            ->placeholder('Never'),
                        Infolists\Components\IconEntry::make('connected')
                            ->label('Connected')
                            ->boolean()
                            ->getStateUsing(fn (Server $record): bool => $record->isConnected()),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Provisioning')
                    ->description('Run this command on your server as root to install the SiteKit agent. The agent will automatically configure Nginx, PHP, databases, and more.')
                    ->icon('heroicon-o-command-line')
                    ->schema([
                        Infolists\Components\TextEntry::make('provisioning_command')
                            ->label('Installation Command')
                            ->getStateUsing(fn (Server $record) => $record->getProvisioningCommand())
                            ->copyable()
                            ->fontFamily('mono')
                            ->columnSpanFull()
                            ->helperText('SSH into your server and paste this command'),
                        Infolists\Components\TextEntry::make('agent_token_expires_at')
                            ->label('Token Expires')
                            ->dateTime()
                            ->visible(fn (Server $record) => $record->isPending() || $record->isProvisioning()),
                    ])
                    ->visible(fn (Server $record) => $record->isPending() || $record->isProvisioning()),

                Infolists\Components\Section::make('Latest Stats')
                    ->description('Real-time resource usage from the last heartbeat.')
                    ->icon('heroicon-o-chart-bar')
                    ->schema([
                        Infolists\Components\TextEntry::make('latestStats.cpu_percent')
                            ->label('CPU')
                            ->suffix('%')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('latestStats.memory_percent')
                            ->label('Memory')
                            ->suffix('%')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('latestStats.disk_percent')
                            ->label('Disk')
                            ->suffix('%')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('latestStats.load_1m')
                            ->label('Load (1m)')
                            ->placeholder('-'),
                    ])
                    ->columns(4)
                    ->visible(fn (Server $record) => $record->isActive()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->copyable()
                    ->placeholder('Pending'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => Server::STATUS_PENDING,
                        'info' => Server::STATUS_PROVISIONING,
                        'success' => Server::STATUS_ACTIVE,
                        'danger' => [Server::STATUS_OFFLINE, Server::STATUS_FAILED],
                    ]),
                Tables\Columns\TextColumn::make('provider')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Server::PROVIDER_DIGITALOCEAN => 'DigitalOcean',
                        Server::PROVIDER_LINODE => 'Linode',
                        Server::PROVIDER_VULTR => 'Vultr',
                        Server::PROVIDER_HETZNER => 'Hetzner',
                        Server::PROVIDER_AWS => 'AWS',
                        default => 'Custom',
                    }),
                Tables\Columns\IconColumn::make('connected')
                    ->label('Connected')
                    ->boolean()
                    ->getStateUsing(fn (Server $record): bool => $record->isConnected()),
                Tables\Columns\TextColumn::make('cpu_percent')
                    ->label('CPU')
                    ->suffix('%')
                    ->getStateUsing(fn (Server $record) => $record->latestStats()?->cpu_percent)
                    ->color(fn ($state) => $state === null ? 'gray' : ($state > 80 ? 'danger' : ($state > 60 ? 'warning' : 'success')))
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('memory_percent')
                    ->label('Memory')
                    ->suffix('%')
                    ->getStateUsing(fn (Server $record) => $record->latestStats()?->memory_percent)
                    ->color(fn ($state) => $state === null ? 'gray' : ($state > 80 ? 'danger' : ($state > 60 ? 'warning' : 'success')))
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('disk_percent')
                    ->label('Disk')
                    ->suffix('%')
                    ->getStateUsing(fn (Server $record) => $record->latestStats()?->disk_percent)
                    ->color(fn ($state) => $state === null ? 'gray' : ($state > 80 ? 'danger' : ($state > 60 ? 'warning' : 'success')))
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_heartbeat_at')
                    ->label('Last Seen')
                    ->since()
                    ->sortable()
                    ->placeholder('Never'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Server::STATUS_PENDING => 'Pending',
                        Server::STATUS_PROVISIONING => 'Provisioning',
                        Server::STATUS_ACTIVE => 'Active',
                        Server::STATUS_OFFLINE => 'Offline',
                        Server::STATUS_FAILED => 'Failed',
                    ]),
                Tables\Filters\SelectFilter::make('provider')
                    ->options([
                        Server::PROVIDER_CUSTOM => 'Custom',
                        Server::PROVIDER_DIGITALOCEAN => 'DigitalOcean',
                        Server::PROVIDER_LINODE => 'Linode',
                        Server::PROVIDER_VULTR => 'Vultr',
                        Server::PROVIDER_HETZNER => 'Hetzner',
                        Server::PROVIDER_AWS => 'AWS',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('refresh')
                    ->label('Refresh')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (Server $record) => $record->isActive())
                    ->action(function (Server $record) {
                        // Request a heartbeat from agent
                        $record->update(['last_heartbeat_at' => now()]);
                        Notification::make()
                            ->title('Refresh Requested')
                            ->body('Server status will update on next agent heartbeat.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('ssh_info')
                    ->label('SSH')
                    ->icon('heroicon-o-command-line')
                    ->color('gray')
                    ->visible(fn (Server $record) => $record->isActive() && $record->ip_address)
                    ->modalHeading('SSH Connection')
                    ->modalDescription('Use these details to connect to your server.')
                    ->modalContent(fn (Server $record) => new HtmlString(
                        '<div class="space-y-4">' .
                        '<div>' .
                        '<label class="text-sm font-medium text-gray-700 dark:text-gray-300">SSH Command</label>' .
                        '<div class="mt-1 bg-gray-900 text-green-400 p-3 rounded-lg font-mono text-sm">' .
                        '<code>ssh root@' . e($record->ip_address) . ($record->ssh_port != 22 ? ' -p ' . e($record->ssh_port) : '') . '</code>' .
                        '</div>' .
                        '</div>' .
                        '<div class="grid grid-cols-2 gap-4">' .
                        '<div><label class="text-sm font-medium text-gray-700 dark:text-gray-300">Host</label><p class="mt-1 font-mono">' . e($record->ip_address) . '</p></div>' .
                        '<div><label class="text-sm font-medium text-gray-700 dark:text-gray-300">Port</label><p class="mt-1 font-mono">' . e($record->ssh_port ?? 22) . '</p></div>' .
                        '</div>' .
                        '</div>'
                    ))
                    ->modalWidth('md')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Tables\Actions\Action::make('ai_diagnose')
                    ->label('AI Diagnose')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->visible(fn (Server $record) => $record->isActive() && config('ai.enabled'))
                    ->extraAttributes(fn (Server $record) => [
                        'x-data' => '',
                        'x-on:click.prevent' => 'openAiChat(' . json_encode(
                            "Analyze the health of my server '{$record->name}' (IP: {$record->ip_address}). " .
                            "Current stats: CPU " . ($record->latestStats()?->cpu_percent ?? 'N/A') . "%, " .
                            "Memory " . ($record->latestStats()?->memory_percent ?? 'N/A') . "%, " .
                            "Disk " . ($record->latestStats()?->disk_percent ?? 'N/A') . "%. " .
                            "OS: " . ($record->os_name ?? 'Unknown') . " " . ($record->os_version ?? '') . ". " .
                            "Identify any issues and suggest optimizations."
                        ) . ')',
                    ]),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('provisioning_script')
                    ->label('Get Script')
                    ->icon('heroicon-o-command-line')
                    ->color('info')
                    ->visible(fn (Server $record) => $record->isPending() || $record->isProvisioning())
                    ->modalHeading('Server Provisioning Script')
                    ->modalDescription('Run this command on your server as root to install the SiteKit agent.')
                    ->modalContent(fn (Server $record) => new HtmlString(
                        '<div class="space-y-4">' .
                        '<div class="bg-gray-900 text-green-400 p-4 rounded-lg font-mono text-sm overflow-x-auto">' .
                        '<code>' . e($record->getProvisioningCommand()) . '</code>' .
                        '</div>' .
                        '<p class="text-sm text-gray-500">Token expires: ' . $record->agent_token_expires_at?->format('M j, Y g:i A') . '</p>' .
                        '</div>'
                    ))
                    ->modalWidth('xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Tables\Actions\Action::make('fix_permissions')
                    ->label('Fix Permissions')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('warning')
                    ->visible(fn (Server $record) => $record->isActive())
                    ->requiresConfirmation()
                    ->modalHeading('Fix Server Permissions')
                    ->modalDescription('This will fix common permission issues that can cause SSL certificate generation to fail. Run this if SSL issuance is failing with "404" errors.')
                    ->action(function (Server $record) {
                        \App\Models\AgentJob::create([
                            'server_id' => $record->id,
                            'team_id' => $record->team_id,
                            'type' => 'fix_permissions',
                            'payload' => [],
                        ]);
                        Notification::make()
                            ->title('Fix Permissions Job Queued')
                            ->body('The server permissions will be fixed shortly. Try issuing your SSL certificate again after a few seconds.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('regenerate_token')
                    ->label('Regenerate Token')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Server $record) => $record->isPending() || $record->isProvisioning())
                    ->requiresConfirmation()
                    ->modalDescription('This will invalidate the current provisioning token and generate a new one.')
                    ->action(function (Server $record) {
                        $record->regenerateAgentToken();
                        Notification::make()
                            ->title('Token Regenerated')
                            ->body('A new provisioning token has been generated. Use the new script to provision your server.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('ai_health_check')
                        ->label('AI Health Check')
                        ->icon('heroicon-o-sparkles')
                        ->color('primary')
                        ->visible(fn () => config('ai.enabled'))
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation()
                        ->modalHeading('AI Health Check')
                        ->modalDescription(fn ($records) => 'Analyze health of ' . $records->count() . ' server(s) with AI?')
                        ->modalSubmitActionLabel('Analyze with AI')
                        ->action(function ($records, $livewire) {
                            $serverList = $records->map(fn ($s) => "- {$s->name} ({$s->ip_address}): CPU " . ($s->latestStats()?->cpu_percent ?? 'N/A') . "%, Memory " . ($s->latestStats()?->memory_percent ?? 'N/A') . "%, Disk " . ($s->latestStats()?->disk_percent ?? 'N/A') . "%")->join("\n");
                            $message = "Analyze the health of these servers and identify any issues:\n\n{$serverList}\n\nWhich servers need attention and what should I prioritize?";
                            $livewire->dispatch('open-ai-chat', message: $message);
                        }),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-server-stack')
            ->emptyStateHeading('No servers yet')
            ->emptyStateDescription('Create your first server to get started. After creating, you\'ll receive a command to run on your VPS to install the SiteKit agent.')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Server')
                    ->icon('heroicon-o-plus'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\WebAppsRelationManager::class,
            RelationManagers\DatabasesRelationManager::class,
            RelationManagers\ServicesRelationManager::class,
            RelationManagers\ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServers::route('/'),
            'create' => Pages\CreateServer::route('/create'),
            'view' => Pages\ViewServer::route('/{record}'),
            'edit' => Pages\EditServer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('team_id', Filament::getTenant()?->id);
    }
}
