<?php

namespace App\Filament\Pages;

use App\Jobs\JotformSyncJob;
use App\Models\JotformSync;
use App\Models\SiHalal as ModelsSiHalal;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Divider;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

class SiHalal extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCloudArrowUp;

    protected static UnitEnum|string|null $navigationGroup = 'Sinkronisasi';

    protected static ?string $navigationLabel = 'SiHalal';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Sihalal Jotform';

    protected string $view = 'filament.pages.si-halal';

    public ?array $data = [];

    protected ?ModelsSiHalal $record = null;

    // Store custom table search value
    public ?string $customTableSearch = null;

    public function mount(): void
    {
        $this->record = ModelsSiHalal::query()
            ->latest()
            ->first();

        if ($this->record) {
            $this->form->fill($this->record->toArray());
        } else {
            $this->form->fill();
        }
    }

    public function updating($name, $value): void
    {
        // Capture table search updates from Livewire
        if ($name === 'tableSearch') {
            \Log::info('Updating Search', [
                'name' => $name,
                'value' => $value,
                'empty' => empty($value),
                'is_null' => is_null($value),
                'old_property' => $this->customTableSearch
            ]);

            // Reset to null if empty, otherwise set the value
            $this->customTableSearch = !empty($value) ? $value : null;

            \Log::info('Updated Search Property', [
                'new_property' => $this->customTableSearch
            ]);
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('JotForm API Configuration')
                    ->description('Enter your JotForm API key and Form ID to enable synchronization.')
                    ->schema([
                        TextInput::make('api_key')
                            ->label('API Key')
                            ->placeholder('Enter your JotForm API key')
                            ->helperText('You can find your API key in JotForm Settings > API'),

                        TextInput::make('form_id')
                            ->label('Form ID')
                            ->placeholder('Enter your JotForm ID')
                            ->helperText('The Form ID can be found in the URL of your form'),
                    ]),

                Section::make('Halal.go.id API Configuration')
                    ->description('Configure the API credentials for submitting factory data to halal.go.id')
                    ->schema([
                        TextInput::make('bearer_token')
                            ->label('Bearer Token')
                            ->placeholder('Enter the Bearer Token from halal.go.id')
                            ->helperText('The authorization token for halal.go.id API'),

                        TextInput::make('pelaku_usaha_uuid')
                            ->label('Pelaku Usaha UUID')
                            ->placeholder('Enter the Pelaku Usaha Profile UUID')
                            ->helperText('The UUID from the pelaku usaha profile URL'),
                    ]),

                Action::make('save')
                    ->label('💾 Simpan Pengaturan')
                    ->action('save'),

                Section::make()
                    ->schema([]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        if ($this->record) {
            $this->record->update($data);
        } else {
            $this->record = ModelsSiHalal::create($data);
        }

        // Refresh form with latest data from database
        $this->record->refresh();
        $this->form->fill($this->record->toArray());

        Notification::make()
            ->title('Konfigurasi berhasil disimpan')
            ->success()
            ->send();
    }

    public function table(Table $table): Table
    {
        // Always get the current search value from request
        $components = request()->input('components', []);
        $currentSearch = null;

        foreach ($components as $component) {
            if (isset($component['updates']['tableSearch'])) {
                $searchValue = $component['updates']['tableSearch'];
                $currentSearch = !empty($searchValue) ? $searchValue : null;
                break;
            }
        }

        \Log::info('Table Search', [
            'property' => $this->customTableSearch,
            'current_search' => $currentSearch,
            'using' => $currentSearch ?? $this->customTableSearch,
            'empty' => empty($currentSearch ?? $this->customTableSearch)
        ]);

        return $table
            ->heading('Data Sinkronisasi JotForm')
            ->query(
                JotformSync::query()->when(
                    !empty($currentSearch ?? $this->customTableSearch),
                    function ($query) use ($currentSearch) {
                        $searchValue = $currentSearch ?? $this->customTableSearch;

                        // Search in dedicated columns (more efficient) and payload
                        $query->where(function ($q) use ($searchValue) {
                            $q->where('nama_lengkap', 'like', "%{$searchValue}%")
                                ->orWhere('email', 'like', "%{$searchValue}%")
                                ->orWhere('nama_sppg', 'like', "%{$searchValue}%")
                                ->orWhere('status_submit', 'like', "%{$searchValue}%")
                                // Also search in payload for other fields
                                ->orWhereRaw('CAST(payload AS CHAR) LIKE ?', ["%{$searchValue}%"]);
                        });
                    }
                )
            )
            ->emptyStateHeading('Belum Ada Data Sinkronisasi')
            ->emptyStateDescription(
                'Klik tombol "Sinkronisasi" untuk mengambil data dari JotForm.'
            )
            ->emptyStateIcon('heroicon-o-arrow-path')
            ->headerActions([
                Action::make('sync')
                    ->label(function () {
                        $isSyncing = cache()->get('jotform_sync_running', false);
                        return $isSyncing ? '⏳ Sedang Mensinkronisasi...' : '🔄 Sinkronisasi Data';
                    })
                    ->action(fn() => $this->syncJotformData())
                    ->color('primary')
                    ->disabled(fn() => cache()->get('jotform_sync_running', false)),
            ])
            ->poll('5s') // Auto refresh table every 5 seconds
            ->columns([
                TextColumn::make('nama_lengkap')
                    ->label('Nama Lengkap')
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->sortable(),

                TextColumn::make('nama_sppg')
                    ->label('Nama SPPG')
                    ->sortable(),

                TextColumn::make('alamat_sppg')
                    ->label('Alamat SPPG'),

                TextColumn::make('status_submit')
                    ->label('Status Submit')
                    ->badge(),

                TextColumn::make('created_at')
                    ->label('Tanggal Sync')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->searchable()
            ->recordActions([
                Action::make('view_detail')
                    ->label('Lihat Detail')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Detail Submission')
                    ->modalContent(function ($record) {
                        return view('filament.modals.submission-detail', ['record' => $record]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
            ]);
    }

    protected function syncJotformData(): void
    {
        // Set sync status to running
        cache()->put('jotform_sync_running', true, now()->addMinutes(10));

        // Dispatch job to handle sync in background
        dispatch(new JotformSyncJob(auth()->id()));

        Notification::make()
            ->title('Sinkronisasi sedang diproses')
            ->body('Data sedang disinkronisasi di latar belakang. Table akan otomatis terupdate.')
            ->info()
            ->send();

        // Refresh the table to show loading state
        $this->dispatch('refresh-table');
    }
}
