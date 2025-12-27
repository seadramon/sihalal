<?php

namespace App\Filament\Pages;

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
                    ->label('💾 Simpan Pengaturan'),

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

        Notification::make()
            ->title('Konfigurasi berhasil disimpan')
            ->success()
            ->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Data Sinkronisasi JotForm')
            ->query(JotformSync::query())
            ->emptyStateHeading('Belum Ada Data Sinkronisasi')
            ->emptyStateDescription(
                'Klik tombol "Sinkronisasi" untuk mengambil data dari JotForm.'
            )
            ->emptyStateIcon('heroicon-o-arrow-path')
            ->headerActions([
                Action::make('sync')
                    ->label('🔄 Sinkronisasi Data')
                    ->action(fn() => $this->syncJotformData()),
            ])
            ->searchable()
            ->columns([
                TextColumn::make('nama_lengkap')
                    ->label('Nama Lengkap')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('nama_sppg')
                    ->label('Nama SPPG'),

                TextColumn::make('alamat_sppg')
                    ->label('Alamat SPPG')
                    ->wrap(),

                TextColumn::make('status_submit')
                    ->label('Status Submit')
                    ->badge(),

                TextColumn::make('synced_at')
                    ->label('Tanggal Sync')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ]);
    }

    protected function syncJotformData(): void
    {
        Notification::make()
            ->title('Sinkronisasi berhasil dijalankan')
            ->success()
            ->send();
    }
}
