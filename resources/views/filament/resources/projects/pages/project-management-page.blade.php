<x-filament-panels::page>
    @php
        $record->loadMissing(['order.user', 'order.product', 'steps']);
    @endphp

    <div style="display: flex; flex-direction: column; gap: 24px;">
        <div style="border: 1px solid #e5e7eb; border-radius: 18px; background: #ffffff; padding: 24px; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);">
            <h2 style="margin: 0; font-size: 22px; font-weight: 700; color: #111827;">Gerenciamento do Projeto</h2>
            <p style="margin: 8px 0 0 0; font-size: 14px; color: #4b5563;">
                {{ $record->order->title }} · {{ $record->order->user->name }}
            </p>
            <div style="margin-top: 14px; font-size: 14px; color: #6b7280;">
                Progresso atual: <strong style="color: #111827;">{{ $this->progressPercentage() }}%</strong>
                · Etapas concluídas: <strong style="color: #111827;">{{ $this->completedStepsCount() }} / {{ $this->totalStepsCount() }}</strong>
            </div>
        </div>

        <div style="display: grid; gap: 24px; grid-template-columns: repeat(1, minmax(0, 1fr));">
            <div style="border: 1px solid #e5e7eb; border-radius: 18px; background: #ffffff; padding: 24px; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);">
                <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #111827;">Dados administrativos</h3>

                <form wire:submit="saveProjectSettings" style="display: flex; flex-direction: column; gap: 12px; margin-top: 16px;">
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-size: 13px; color: #374151;">Status do pagamento</label>
                        <select
                            wire:model="quickData.payment_status"
                            style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 8px 10px; font-size: 14px;"
                        >
                            @foreach(\App\Models\Project::PAYMENT_STATUSES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 6px; font-size: 13px; color: #374151;">URL do GitHub</label>
                        <input
                            type="url"
                            wire:model="quickData.github_url"
                            placeholder="https://github.com/..."
                            style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 8px 10px; font-size: 14px;"
                        />
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 6px; font-size: 13px; color: #374151;">URL de Deploy</label>
                        <input
                            type="url"
                            wire:model="quickData.deploy_url"
                            placeholder="https://..."
                            style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 8px 10px; font-size: 14px;"
                        />
                    </div>

                    <div>
                        <button
                            type="submit"
                            style="border: none; border-radius: 6px; background: #111827; color: #ffffff; cursor: pointer; padding: 8px 14px; font-size: 13px;"
                        >
                            Salvar alterações
                        </button>
                    </div>
                </form>
            </div>

            <div style="border: 1px solid #e5e7eb; border-radius: 18px; background: #ffffff; padding: 24px; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);">
                <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #111827;">Avanço de etapas</h3>

                <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 16px;">
                    @forelse($record->steps->sortBy('id') as $step)
                        @php
                            $stepLabelStyle = 'font-size: 12px; font-weight: 700; color: ' . ($step->is_completed ? '#047857' : '#b45309') . ';';
                            $stepButtonStyle = 'border: none; border-radius: 6px; background: ' . ($step->is_completed ? '#dc2626' : '#16a34a') . '; color: #ffffff; cursor: pointer; padding: 6px 10px; font-size: 12px;';
                        @endphp
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; border: 1px solid #e5e7eb; border-radius: 12px; padding: 12px 14px;">
                            <div>
                                <div style="font-size: 14px; font-weight: 700; color: #111827;">{{ $step->title }}</div>
                                <div style="font-size: 12px; color: #6b7280;">Peso: {{ $step->weight }}</div>
                            </div>

                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="<?php echo e($stepLabelStyle); ?>">
                                    {{ $step->is_completed ? 'Concluída' : 'Pendente' }}
                                </span>
                                <button
                                    type="button"
                                    wire:click="toggleStepCompletion({{ $step->id }})"
                                    style="<?php echo e($stepButtonStyle); ?>"
                                >
                                    {{ $step->is_completed ? 'Reabrir' : 'Concluir' }}
                                </button>
                            </div>
                        </div>
                    @empty
                        <div style="border: 1px dashed #d1d5db; border-radius: 12px; padding: 14px; font-size: 14px; color: #6b7280;">
                            Nenhuma etapa cadastrada para este projeto.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
