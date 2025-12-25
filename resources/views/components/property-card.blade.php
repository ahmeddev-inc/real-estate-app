{{-- Property Card Component --}}
@props(['property'])

<div {{ $attributes->merge(['class' => 'aaker-card overflow-hidden']) }}>
    <!-- Property Image -->
    <div class="relative h-48 bg-gray-200">
        @if(isset($property['images']) && count($property['images']) > 0)
            <img 
                src="{{ $property['images'][0] }}" 
                alt="{{ $property['title'] ?? 'عقار' }}"
                class="w-full h-full object-cover"
            >
        @else
            <div class="w-full h-full flex items-center justify-center bg-gradient-to-r from-blue-50 to-blue-100">
                <svg class="w-12 h-12 text-blue-300" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                </svg>
            </div>
        @endif
        
        <!-- Status Badge -->
        @if(isset($property['status']))
            <div class="absolute top-3 left-3">
                <span class="status-badge status-{{ $property['status'] }}">
                    {{ $this->getStatusText($property['status']) }}
                </span>
            </div>
        @endif
        
        <!-- Featured Badge -->
        @if(isset($property['is_featured']) && $property['is_featured'])
            <div class="absolute top-3 right-3">
                <span class="bg-yellow-500 text-white px-2 py-1 rounded-full text-xs font-bold">
                    ★ مميز
                </span>
            </div>
        @endif
    </div>
    
    <!-- Property Details -->
    <div class="p-4">
        <!-- Price -->
        <div class="mb-3">
            <span class="text-2xl font-bold text-primary-600">
                {{ number_format($property['price_egp'] ?? 0) }} ج.م
            </span>
            @if(isset($property['price_per_m2']))
                <span class="text-sm text-gray-500">
                    ({{ number_format($property['price_per_m2']) }} ج.م/م²)
                </span>
            @endif
        </div>
        
        <!-- Title -->
        <h3 class="text-lg font-semibold text-gray-900 mb-2">
            {{ $property['title'] ?? 'عقار بدون عنوان' }}
        </h3>
        
        <!-- Location -->
        <div class="flex items-center text-gray-600 mb-3">
            <svg class="w-4 h-4 ml-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
            </svg>
            <span class="text-sm">
                {{ $property['location'] ?? 'غير محدد' }}، {{ $property['city'] ?? 'غير محدد' }}
            </span>
        </div>
        
        <!-- Features -->
        <div class="flex items-center justify-between border-t border-gray-100 pt-3">
            @if(isset($property['bedrooms']))
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-gray-400 ml-1" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                    </svg>
                    <span class="text-sm text-gray-600">{{ $property['bedrooms'] }} غرف</span>
                </div>
            @endif
            
            @if(isset($property['bathrooms']))
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-gray-400 ml-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M11 3a1 1 0 10-2 0v1a1 1 0 102 0V3zm-5.536 5.464a1 1 0 001.415 0 3 3 0 014.242 0 1 1 0 001.415-1.415 5 5 0 00-7.072 0 1 1 0 000 1.415zM4 11a1 1 0 100-2 1 1 0 000 2zm13 1a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1zm-9 3a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/>
                    </svg>
                    <span class="text-sm text-gray-600">{{ $property['bathrooms'] }} حمام</span>
                </div>
            @endif
            
            @if(isset($property['area_m2']))
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-gray-400 ml-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V8a2 2 0 00-2-2h-5L9 4H4zm7 5a1 1 0 00-2 0v1H8a1 1 0 000 2h1v1a1 1 0 002 0v-1h1a1 1 0 000-2h-1V9z" clip-rule="evenodd"/>
                    </svg>
                    <span class="text-sm text-gray-600">{{ $property['area_m2'] }} م²</span>
                </div>
            @endif
        </div>
        
        <!-- Actions -->
        <div class="mt-4 flex space-x-2 space-x-reverse">
            <button class="btn-primary flex-1 text-center py-2">
                عرض التفاصيل
            </button>
            <button class="border border-primary-500 text-primary-600 rounded-lg px-4 py-2 hover:bg-primary-50 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
            </button>
        </div>
    </div>
</div>

@php
    function getStatusText($status) {
        $statuses = [
            'draft' => 'مسودة',
            'published' => 'منشور',
            'available' => 'متاح',
            'reserved' => 'محجوز',
            'sold' => 'مباع',
            'rented' => 'مؤجر',
        ];
        return $statuses[$status] ?? $status;
    }
@endphp
