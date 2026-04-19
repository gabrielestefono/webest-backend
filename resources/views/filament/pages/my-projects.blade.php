<x-filament-panels::page>
    @php
        $projects = $this->projects();
    @endphp

    <div style="display: flex; flex-direction: column; gap: 24px;">
        <div style="border: 1px solid #e5e7eb; border-radius: 18px; background: #ffffff; padding: 24px; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);">
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <p style="margin: 0; font-size: 14px; font-weight: 500; color: #6b7280;">Painel do cliente</p>
                <h2 style="margin: 0; font-size: 24px; font-weight: 700; letter-spacing: -0.02em; color: #111827;">Meus Projetos</h2>
                <p style="margin: 0; font-size: 14px; color: #4b5563;">Acompanhe o andamento dos seus projetos e acesse o hub de cada um.</p>
            </div>
        </div>

        @if($projects->isEmpty())
            <div style="border: 1px dashed #d1d5db; border-radius: 18px; background: #ffffff; padding: 28px 24px; color: #6b7280; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);">
                Você ainda não possui projetos vinculados a pedidos aprovados.
            </div>
        @else
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px;">
                @foreach($projects as $project)
                    @php
                        $progress = (int) $project->current_progress;
                        $projectProgressStyle = 'width: ' . $progress . '%;';
                    @endphp

                    <div style="border: 1px solid #e5e7eb; border-radius: 18px; background: #ffffff; padding: 24px; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05); display: flex; flex-direction: column; gap: 16px;">
                        <div style="display: flex; flex-direction: column; gap: 6px;">
                            <div style="font-size: 12px; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; color: #6b7280;">
                                Projeto #{{ $project->id }}
                            </div>
                            <div style="font-size: 18px; font-weight: 700; color: #111827;">
                                {{ $project->order->title }}
                            </div>
                            <div style="font-size: 14px; color: #4b5563;">
                                {{ $project->order->product->name }}
                            </div>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; font-size: 14px; color: #4b5563;">
                                <span>Progresso</span>
                                <strong style="color: #111827;">{{ $progress }}%</strong>
                            </div>
                            <div style="width: 100%; height: 10px; overflow: hidden; border-radius: 9999px; background: #e5e7eb;">
                                <div style="<?php echo e('height: 100%; border-radius: 9999px; background: #4f46e5;' . $projectProgressStyle); ?>"></div>
                            </div>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 8px; font-size: 14px; color: #4b5563;">
                            <div>Pagamento: <strong style="color: #111827;">{{ ucfirst($project->payment_status) }}</strong></div>
                            <div>Etapas: <strong style="color: #111827;">{{ $project->steps->where('is_completed', true)->count() }} / {{ $project->steps->count() }}</strong></div>
                        </div>

                        <a
                            href="{{ $this->projectUrl($project) }}"
                            style="display: inline-flex; align-items: center; justify-content: center; border-radius: 12px; background: #111827; padding: 12px 16px; font-size: 14px; font-weight: 700; color: #ffffff; text-decoration: none;"
                        >
                            Abrir hub do projeto
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
