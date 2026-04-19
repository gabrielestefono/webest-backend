<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{ state: $wire.$entangle(@js($getStatePath())) }"
        {{ $getExtraAttributeBag() }}
    >
        <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));">
            @forelse ($getProducts() as $product)
                <button
                    type="button"
                    @click="state = {{ $product->id }}"
                    style="
                        display: flex;
                        flex-direction: column;
                        justify-content: space-between;
                        height: 100%;
                        padding: 1.25rem;
                        background-color: #ffffff;
                        border: 1px solid #e5e7eb;
                        border-radius: 1rem;
                        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
                        text-align: left;
                        cursor: pointer;
                        position: relative;
                        overflow: hidden;
                    "
                    :style="state == {{ $product->id }} ? {
                        borderColor: '#3b82f6',
                        boxShadow: '0 0 0 3px rgba(59, 130, 246, 0.1), 0 4px 12px 0 rgba(0, 0, 0, 0.1)',
                    } : {}"
                >
                    <div style="position: absolute; top: -1.5rem; right: -1.5rem; width: 4rem; height: 4rem; border-radius: 9999px; background: linear-gradient(135deg, rgba(59, 130, 246, 0.4), rgba(59, 130, 246, 0.1)); filter: blur(2rem); pointer-events: none;"></div>

                    <div style="position: relative; margin-bottom: 1rem;">
                        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem;">
                            <div>
                                <h3 style="font-size: 1rem; font-weight: 600; color: #111827; margin: 0;">
                                    {{ $product->name }}
                                </h3>

                                @if ($product->description)
                                    <p style="font-size: 0.875rem; color: #6b7280; margin: 0.25rem 0 0 0;">
                                        {{ $product->description }}
                                    </p>
                                @endif
                            </div>

                            <span style="
                                display: inline-flex;
                                align-items: center;
                                justify-content: center;
                                padding: 0.375rem 0.75rem;
                                background-color: #eff6ff;
                                color: #1e40af;
                                border-radius: 0.5rem;
                                font-size: 0.75rem;
                                font-weight: 600;
                                flex-shrink: 0;
                                white-space: nowrap;
                            ">
                                R$ {{ number_format((float) $product->base_price, 2, ',', '.') }}
                            </span>
                        </div>
                    </div>

                    <div style="
                        position: relative;
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        padding-top: 1rem;
                        border-top: 1px solid #f3f4f6;
                        font-size: 0.875rem;
                    ">
                        <span style="color: #6b7280; font-weight: 500; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em;">
                            Selecionar produto
                        </span>

                        <span
                            style="
                                display: inline-flex;
                                align-items: center;
                                gap: 0.375rem;
                                padding: 0.375rem 0.75rem;
                                border-radius: 9999px;
                                font-size: 0.75rem;
                                font-weight: 600;
                                transition: all 0.3s ease;
                            "
                            :style="state == {{ $product->id }} ? {
                                backgroundColor: '#10b981',
                                color: '#ffffff',
                            } : {
                                backgroundColor: '#f3f4f6',
                                color: '#4b5563',
                            }"
                        >
                            <svg x-show="state == {{ $product->id }}" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 0.875rem; height: 0.875rem;">
                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-8 8a1 1 0 0 1-1.415.001l-4-4a1 1 0 1 1 1.414-1.415l3.293 3.294 7.293-7.294a1 1 0 0 1 1.409 0Z" clip-rule="evenodd" />
                            </svg>
                            <span x-show="state == {{ $product->id }}" x-cloak>Selecionado</span>
                            <span x-show="state != {{ $product->id }}" x-cloak>Escolher</span>
                        </span>
                    </div>
                </button>
            @empty
                <div style="
                    grid-column: 1 / -1;
                    padding: 2rem;
                    background-color: #f9fafb;
                    border: 2px dashed #d1d5db;
                    border-radius: 1rem;
                    text-align: center;
                ">
                    <div style="
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        width: 2.5rem;
                        height: 2.5rem;
                        margin: 0 auto 0.75rem;
                        background-color: #e5e7eb;
                        border-radius: 9999px;
                        color: #6b7280;
                    ">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 1.25rem; height: 1.25rem;">
                            <path d="M11.25 4.533A9.707 9.707 0 0 1 12 4.5c2.6 0 4.977 1.02 6.75 2.684a9.706 9.706 0 0 1 .75 13.737A9.706 9.706 0 0 1 12 22.5a9.706 9.706 0 0 1-7.5-3.579A9.706 9.706 0 0 1 3.75 5.184a9.704 9.704 0 0 1 7.5-.651ZM8.22 8.22a.75.75 0 0 0-1.06 1.06L10.94 13l-3.78 3.72a.75.75 0 1 0 1.06 1.06L12 14.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L13.06 13l3.72-3.72a.75.75 0 0 0-1.06-1.06L12 11.94 8.22 8.22Z" />
                        </svg>
                    </div>
                    <p style="font-weight: 600; color: #374151; margin: 0; font-size: 0.875rem;">
                        Nenhum produto ativo disponível
                    </p>
                    <p style="color: #6b7280; margin: 0.25rem 0 0 0; font-size: 0.75rem;">
                        Assim que houver produtos ativos, eles aparecerão aqui para seleção.
                    </p>
                </div>
            @endforelse
        </div>
    </div>
</x-dynamic-component>
