<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PropertyResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Http;

class PropertyResource extends Resource
{
    protected static ?string $model = null; // سيتم توصيله لاحقاً
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationGroup = 'العقارات';
    protected static ?string $navigationLabel = 'العقارات';
    protected static ?int $navigationSort = 1;
    
    // Mock Data للعرض
    public static function getMockProperties()
    {
        return [
            [
                'id' => 1,
                'code' => 'PROP-001',
                'title' => 'فيلا فاخرة في المعادي',
                'type' => 'villa',
                'price_egp' => 5000000,
                'city' => 'القاهرة',
                'location' => 'المعادي',
                'status' => 'available',
                'bedrooms' => 4,
                'bathrooms' => 3,
                'area_m2' => 300,
                'is_featured' => true,
                'images' => ['https://via.placeholder.com/400x300/3B82F6/FFFFFF?text=Villa'],
            ],
            [
                'id' => 2,
                'code' => 'PROP-002',
                'title' => 'شقة جديدة في التجمع الخامس',
                'type' => 'apartment',
                'price_egp' => 2500000,
                'city' => 'القاهرة',
                'location' => 'التجمع الخامس',
                'status' => 'reserved',
                'bedrooms' => 3,
                'bathrooms' => 2,
                'area_m2' => 180,
                'is_featured' => false,
                'images' => ['https://via.placeholder.com/400x300/10B981/FFFFFF?text=Apartment'],
            ],
            [
                'id' => 3,
                'code' => 'PROP-003',
                'title' => 'محل تجاري في مدينة نصر',
                'type' => 'commercial',
                'price_egp' => 3000000,
                'city' => 'القاهرة',
                'location' => 'مدينة نصر',
                'status' => 'available',
                'area_m2' => 120,
                'is_featured' => true,
                'images' => ['https://via.placeholder.com/400x300/EF4444/FFFFFF?text=Commercial'],
            ],
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات العقار الأساسية')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('عنوان العقار')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('مثال: فيلا فاخرة في المعادي')
                            ->columnSpanFull(),
                        
                        Forms\Components\Textarea::make('description')
                            ->label('الوصف')
                            ->rows(3)
                            ->placeholder('وصف تفصيلي للعقار...')
                            ->columnSpanFull(),
                    ]),
                
                Forms\Components\Section::make('التفاصيل الفنية')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('نوع العقار')
                            ->options([
                                'apartment' => 'شقة',
                                'villa' => 'فيلا',
                                'duplex' => 'دوبلكس',
                                'townhouse' => 'تاون هاوس',
                                'penthouse' => 'بنتهاوس',
                                'studio' => 'استوديو',
                                'land' => 'أرض',
                                'commercial' => 'تجاري',
                                'office' => 'مكتب',
                                'shop' => 'محل',
                            ])
                            ->required()
                            ->native(false)
                            ->searchable(),
                        
                        Forms\Components\Select::make('status')
                            ->label('الحالة')
                            ->options([
                                'draft' => 'مسودة',
                                'published' => 'منشور',
                                'available' => 'متاح',
                                'reserved' => 'محجوز',
                                'sold' => 'مباع',
                                'rented' => 'مؤجر',
                            ])
                            ->default('draft')
                            ->required()
                            ->native(false),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('السعر والمساحة')
                    ->schema([
                        Forms\Components\TextInput::make('price_egp')
                            ->label('السعر (جنيه مصري)')
                            ->numeric()
                            ->required()
                            ->prefix('ج.م')
                            ->placeholder('مثال: 2500000'),
                        
                        Forms\Components\TextInput::make('area_m2')
                            ->label('المساحة (م²)')
                            ->numeric()
                            ->required()
                            ->suffix('م²')
                            ->placeholder('مثال: 180'),
                        
                        Forms\Components\TextInput::make('bedrooms')
                            ->label('عدد الغرف')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('مثال: 3'),
                        
                        Forms\Components\TextInput::make('bathrooms')
                            ->label('عدد الحمامات')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('مثال: 2'),
                    ])
                    ->columns(4),
                
                Forms\Components\Section::make('الموقع')
                    ->schema([
                        Forms\Components\Select::make('city')
                            ->label('المدينة')
                            ->options([
                                'القاهرة' => 'القاهرة',
                                'الجيزة' => 'الجيزة',
                                'الإسكندرية' => 'الإسكندرية',
                                'المنصورة' => 'المنصورة',
                                'الزقازيق' => 'الزقازيق',
                            ])
                            ->required()
                            ->searchable(),
                        
                        Forms\Components\TextInput::make('location')
                            ->label('المنطقة')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('مثال: المعادي، التجمع الخامس'),
                        
                        Forms\Components\Textarea::make('address')
                            ->label('العنوان التفصيلي')
                            ->rows(2)
                            ->placeholder('العنوان بالتفصيل...')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('المرفقات')
                    ->schema([
                        Forms\Components\FileUpload::make('images')
                            ->label('صور العقار')
                            ->multiple()
                            ->image()
                            ->directory('properties')
                            ->maxFiles(20)
                            ->reorderable()
                            ->columnSpanFull(),
                        
                        Forms\Components\FileUpload::make('documents')
                            ->label('المستندات')
                            ->multiple()
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxFiles(10)
                            ->directory('properties/documents')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('images')
                    ->label('صورة')
                    ->circular()
                    ->size(50)
                    ->defaultImageUrl('https://via.placeholder.com/50/3B82F6/FFFFFF?text=P'),
                
                Tables\Columns\TextColumn::make('code')
                    ->label('الكود')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn ($record) => $record['title'] ?? ''),
                
                Tables\Columns\BadgeColumn::make('type')
                    ->label('النوع')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'apartment' => 'شقة',
                        'villa' => 'فيلا',
                        'duplex' => 'دوبلكس',
                        'townhouse' => 'تاون هاوس',
                        'penthouse' => 'بنتهاوس',
                        'studio' => 'استوديو',
                        'land' => 'أرض',
                        'commercial' => 'تجاري',
                        'office' => 'مكتب',
                        'shop' => 'محل',
                        default => $state,
                    })
                    ->colors([
                        'primary' => 'apartment',
                        'success' => 'villa',
                        'warning' => 'land',
                        'danger' => 'commercial',
                    ]),
                
                Tables\Columns\TextColumn::make('price_egp')
                    ->label('السعر')
                    ->formatStateUsing(fn ($state) => number_format($state) . ' ج.م')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('city')
                    ->label('المدينة')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors([
                        'warning' => 'draft',
                        'success' => 'published',
                        'info' => 'available',
                        'danger' => 'reserved',
                        'gray' => 'sold',
                    ])
                    ->formatStateUsing(fn ($state) => match($state) {
                        'draft' => 'مسودة',
                        'published' => 'منشور',
                        'available' => 'متاح',
                        'reserved' => 'محجوز',
                        'sold' => 'مباع',
                        'rented' => 'مؤجر',
                        default => $state,
                    }),
                
                Tables\Columns\IconColumn::make('is_featured')
                    ->label('مميز')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseIcon('heroicon-o-star')
                    ->falseColor('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('نوع العقار')
                    ->options([
                        'apartment' => 'شقة',
                        'villa' => 'فيلا',
                        'duplex' => 'دوبلكس',
                        'townhouse' => 'تاون هاوس',
                        'penthouse' => 'بنتهاوس',
                        'studio' => 'استوديو',
                        'land' => 'أرض',
                        'commercial' => 'تجاري',
                        'office' => 'مكتب',
                        'shop' => 'محل',
                    ]),
                
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'draft' => 'مسودة',
                        'published' => 'منشور',
                        'available' => 'متاح',
                        'reserved' => 'محجوز',
                        'sold' => 'مباع',
                        'rented' => 'مؤجر',
                    ]),
                
                Tables\Filters\SelectFilter::make('city')
                    ->label('المدينة')
                    ->options([
                        'القاهرة' => 'القاهرة',
                        'الجيزة' => 'الجيزة',
                        'الإسكندرية' => 'الإسكندرية',
                        'المنصورة' => 'المنصورة',
                        'الزقازيق' => 'الزقازيق',
                    ]),
                
                Tables\Filters\Filter::make('is_featured')
                    ->label('العقارات المميزة')
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('عرض'),
                
                Tables\Actions\EditAction::make()
                    ->label('تعديل'),
                
                Tables\Actions\Action::make('publish')
                    ->label('نشر')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        // TODO: Implement publish logic
                        session()->flash('success', 'تم نشر العقار بنجاح');
                    })
                    ->visible(fn ($record) => ($record['status'] ?? '') === 'draft'),
                
                Tables\Actions\DeleteAction::make()
                    ->label('حذف'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('publish_selected')
                        ->label('نشر المحدد')
                        ->icon('heroicon-o-check-circle')
                        ->action(function ($records) {
                            // TODO: Implement bulk publish
                            session()->flash('success', 'تم نشر العقارات المحددة');
                        }),
                    
                    Tables\Actions\BulkAction::make('mark_featured')
                        ->label('تعيين كمميز')
                        ->icon('heroicon-o-star')
                        ->action(function ($records) {
                            // TODO: Implement bulk feature
                            session()->flash('success', 'تم تعيين العقارات كمميزة');
                        }),
                    
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('حذف المحدد'),
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50])
            ->striped();
    }

    public static function getRelations(): array
    {
        return [
            // سيتم إضافة Relations لاحقاً
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProperties::route('/'),
            'create' => Pages\CreateProperty::route('/create'),
            'view' => Pages\ViewProperty::route('/{record}'),
            'edit' => Pages\EditProperty::route('/{record}/edit'),
        ];
    }
}
