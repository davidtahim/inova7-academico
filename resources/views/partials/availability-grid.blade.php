{{--
    Grade de disponibilidade docente (CCG-FOR-02), compartilhada entre a tela da coordenação
    e o perfil do professor. Variáveis: AvailabilityGrid::build() + professor e isEditable.
--}}
<div class="availability-grid-component">
        <div class="row g-3">
            <!-- Calendário de Disponibilidade -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0">
                        <h5 class="mb-0">Grade de Horários {{ $isEditable ? '(Editável - Clique para mudar)' : '(Visualização)' }}</h5>
                    </div>
                    <div class="card-body">
                        @if($isEditable)
                            <div class="alert alert-info mb-3" role="alert">
                                <strong>✏️ Clique nos horários</strong> para mudar o estado:
                                <br><small>⚪ Cinza → 🟢 Verde (Preferido) → 🔵 Azul (Disponível) → ⚪ Cinza (Indisponível)</small>
                            </div>
                        @endif
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered availability-grid">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center" style="width: 12%;">Horário</th>
                                        <th class="text-center">Seg</th>
                                        <th class="text-center">Ter</th>
                                        <th class="text-center">Qua</th>
                                        <th class="text-center">Qui</th>
                                        <th class="text-center">Sex</th>
                                        <th class="text-center">Sáb</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($timeSlots as $slot)
                                        <tr>
                                            <td class="text-center fw-bold small">{{ $slot['time'] }}</td>
                                            @foreach([1, 2, 3, 4, 5, 6] as $dayNum)
                                                @php
                                                    $horario = $slot['start'] . ' - ' . $slot['end'];
                                                    $status = $savedAvailabilities[$dayNum][$horario] ?? 'unavailable';
                                                    
                                                    $class = match($status) {
                                                        'preferred' => 'bg-success text-white',
                                                        'available' => 'bg-info text-white',
                                                        'unavailable' => 'bg-light text-muted',
                                                        default => 'bg-light'
                                                    };
                                                    $label = match($status) {
                                                        'preferred' => '✓ Preferido',
                                                        'available' => '◐ Disponível',
                                                        'unavailable' => '✗ Indisponível',
                                                        default => '—'
                                                    };
                                                    $cellId = "slot-{$dayNum}-{$slot['start']}-{$slot['end']}";
                                                @endphp
                                                <td class="text-center p-0">
                                                    <div class="availability-cell {{ $class }}" 
                                                         data-cell-id="{{ $cellId }}"
                                                         data-weekday="{{ $dayNum }}"
                                                         data-start="{{ $slot['start'] }}"
                                                         data-end="{{ $slot['end'] }}"
                                                         data-status="{{ $status }}"
                                                         title="{{ ['', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'][$dayNum] }}, {{ $slot['time'] }}" @if($isEditable) role="button" tabindex="0" @endif
                                                         style="cursor: {{ $isEditable ? 'pointer' : 'default' }};">
                                                        <small>{{ $label }}</small>
                                                    </div>
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Legenda e Resumo -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-light border-0">
                        <h5 class="mb-0">Legenda</h5>
                    </div>
                    <div class="card-body">
                        @if($isEditable)
                            <p class="text-muted small mb-3">Clique nos horários para alternar entre os estados:</p>
                        @endif
                        <div class="mb-3">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-success" style="min-width: 50px;">✓</span>
                                <span>Preferido - Horário preferencial</span>
                            </div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-info" style="min-width: 50px;">◐</span>
                                <span>Disponível - Pode estar</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-light text-muted" style="min-width: 50px;">✗</span>
                                <span>Indisponível - Não pode estar</span>
                            </div>
                        </div>
                        @if($isEditable)
                            <div class="alert alert-info mb-0" role="alert">
                                <small><strong>Dica:</strong> Clique várias vezes para mudar entre os estados!</small>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-light border-0">
                        <h5 class="mb-0">Resumo</h5>
                    </div>
                    <div class="card-body">
                        <div class="stat-row mb-2">
                            <span class="text-muted">Total de horários</span>
                            <strong id="totalSlots">{{ $totalSlots }}</strong>
                        </div>
                        <div class="stat-row mb-2">
                            <span class="text-muted">Preferidos</span>
                            <span class="badge bg-success" id="preferredCount">{{ $preferredCount }}</span>
                        </div>
                        <div class="stat-row mb-2">
                            <span class="text-muted">Disponíveis</span>
                            <span class="badge bg-info" id="availableCount">{{ $availableCount }}</span>
                        </div>
                        <div class="stat-row">
                            <span class="text-muted">Indisponíveis</span>
                            <span class="badge bg-light text-muted" id="unavailableCount">{{ $unavailableCount }}</span>
                        </div>
                    </div>
                </div>

                @if($term)
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-0">
                            <h5 class="mb-0">Informações</h5>
                        </div>
                        <div class="card-body small">
                            <div class="mb-2">
                                <strong>Semestre:</strong><br>
                                {{ $term->code }}
                                @if($term->starts_at)
                                    <br><strong>Período:</strong><br>
                                    {{ $term->starts_at->format('d/m/Y') }} a {{ $term->ends_at->format('d/m/Y') }}
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Detalhamento de Horários (Carregado após salvar) -->
        @if($isEditable)
        <div id="detalhamentoContainer" style="margin-top: 30px; display: none;">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light border-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">📋 Detalhamento de Horários</h5>
                        <small class="text-muted">Horários salvos em tempo real - Clique para editar ou excluir</small>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="detalhamentoTable" class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 15%;">Dia da Semana</th>
                                    <th style="width: 15%;">Horário</th>
                                    <th style="width: 15%;">Preferência</th>
                                    <th style="width: 35%;">Status</th>
                                    <th style="width: 20%;" class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="detalhamentoContent">
                                <!-- Conteúdo carregado via JavaScript -->
                            </tbody>
                            <tfoot class="table-light" id="detalhamentoFooter" style="display: none;">
                                <tr class="fw-bold">
                                    <td colspan="5" class="text-end">
                                        <span class="me-3">Total: <span id="footerTotal">0</span></span>
                                        <span class="badge bg-success me-2">✓ Preferidos: <span id="footerPreferred">0</span></span>
                                        <span class="badge bg-info me-2">◐ Disponíveis: <span id="footerAvailable">0</span></span>
                                        <span class="badge bg-light text-muted">✗ Indisponíveis: <span id="footerUnavailable">0</span></span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Modal para Editar Preferência -->
        @if($isEditable)
        <div class="modal fade" id="editPreferenceModal" tabindex="-1">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar Preferência</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3"><strong id="editSlotInfo"></strong></p>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="preference" id="pref_preferred" value="preferred" autocomplete="off">
                            <label class="btn btn-outline-success" for="pref_preferred">
                                ✓ Preferido
                            </label>

                            <input type="radio" class="btn-check" name="preference" id="pref_available" value="available" autocomplete="off">
                            <label class="btn btn-outline-info" for="pref_available">
                                ◐ Disponível
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="savePreferenceBtn">Salvar</button>
                    </div>
                </div>
            </div>
        </div>
        @endif
</div>

    <style>
        .availability-grid {
            margin-bottom: 0;
        }

        .availability-cell {
            padding: 8px 4px !important;
            min-height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            border-radius: 4px;
        }

        .availability-cell:hover {
            opacity: 0.8;
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .stat-row:last-child {
            border-bottom: none;
        }

        .availability-cell:focus-visible {
            outline: 3px solid #1264a3;
            outline-offset: -3px;
        }

        .availability-cell.cursor-pointer {
            cursor: pointer;
        }

        .availability-cell.cursor-pointer:hover {
            box-shadow: inset 0 0 0 2px rgba(0,0,0,0.1);
        }
    </style>

    @if($isEditable)
    <script>
        console.log('Script de edição carregado. isEditable:', true);
        const formAction = "{{ route('admin.professors.update-availability', $professor) }}";
        const csrfToken = "{{ csrf_token() }}";
        let selectedSlots = new Map();
        let saveTimeout;

        // Aguardar DOM estar pronto antes de executar qualquer inicialização
        document.addEventListener('DOMContentLoaded', function() {
            console.log('✓ DOM carregado. Iniciando...');
            
            // Inicializar slots já selecionados a partir do banco de dados
            // As células já têm data-status preenchido com os valores do banco
            document.querySelectorAll('.availability-cell').forEach(cell => {
                const status = cell.dataset.status;
                const weekday = cell.dataset.weekday;
                const start = cell.dataset.start;
                const end = cell.dataset.end;
                
                console.log('Célula encontrada:', {
                    weekday: weekday,
                    start: start,
                    end: end,
                    status: status
                });
                
                if (status !== 'unavailable') {
                    const key = `${weekday}-${start}-${end}`;
                    selectedSlots.set(key, status);
                    console.log('✓ Slot carregado do BD:', key, '=', status);
                }
            });
            
            console.log('✓ Slots inicializados do banco de dados');
            console.log('selectedSlots.size:', selectedSlots.size);
            console.log('selectedSlots:', Array.from(selectedSlots.entries()));
            
            // Renderizar detalhamento na inicialização
            console.log('Chamando renderDetalhamento() na inicialização...');
            renderDetalhamento();
            console.log('✓ renderDetalhamento() completo');
            
            // Um único tratador por célula (antes havia também um onclick inline, e cada clique
            // avançava dois estados: Indisponível → Preferido → Disponível).
            document.querySelectorAll('.availability-cell').forEach(cell => {
                cell.addEventListener('click', function () {
                    toggleAvailability(this);
                });
                cell.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        toggleAvailability(this);
                    }
                });
            });
            
            // Salvar mudança de preferência
            const saveBtn = document.getElementById('savePreferenceBtn');
            if (saveBtn) {
                saveBtn.addEventListener('click', function() {
                    const newPreference = document.querySelector('input[name="preference"]:checked').value;
                    const slotKey = window.currentEditSlot;
                    
                    if (slotKey && newPreference) {
                        // Atualizar no Map
                        selectedSlots.set(slotKey, newPreference);
                        
                        // Fechar modal
                        bootstrap.Modal.getInstance(document.getElementById('editPreferenceModal')).hide();
                        
                        // Atualizar resumo
                        updateSummary();
                        
                        // Atualizar detalhamento
                        renderDetalhamento();
                        
                        // Agendar auto-save
                        clearTimeout(saveTimeout);
                        saveTimeout = setTimeout(() => {
                            saveAvailability();
                        }, 1000);
                    }
                });
            }
        });

        function toggleAvailability(element) {
            console.log('toggleAvailability chamado', element.dataset);
            
            const weekday = element.dataset.weekday;
            const start = element.dataset.start;
            const end = element.dataset.end;
            const key = `${weekday}-${start}-${end}`;
            
            const currentStatus = element.dataset.status;
            console.log('Status atual:', currentStatus);
            let newStatus;

            // Ciclo: unavailable → preferred → available → unavailable
            switch(currentStatus) {
                case 'unavailable':
                    newStatus = 'preferred';
                    element.className = 'availability-cell bg-success text-white';
                    element.innerHTML = '<small>✓ Preferido</small>';
                    selectedSlots.set(key, 'preferred');
                    break;
                case 'preferred':
                    newStatus = 'available';
                    element.className = 'availability-cell bg-info text-white';
                    element.innerHTML = '<small>◐ Disponível</small>';
                    selectedSlots.set(key, 'available');
                    break;
                case 'available':
                    newStatus = 'unavailable';
                    element.className = 'availability-cell bg-light text-muted';
                    element.innerHTML = '<small>✗ Indisponível</small>';
                    selectedSlots.delete(key);
                    break;
                default:
                    newStatus = 'unavailable';
                    element.className = 'availability-cell bg-light text-muted';
                    element.innerHTML = '<small>✗ Indisponível</small>';
                    selectedSlots.delete(key);
            }

            element.dataset.status = newStatus;
            console.log('Novo status:', newStatus);
            updateSummary();
            
            // Auto-salvar após 1 segundo de inatividade
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => {
                saveAvailability();
            }, 1000);
        }

        function updateSummary() {
            let preferred = 0;
            let available = 0;

            document.querySelectorAll('.availability-cell').forEach(cell => {
                const status = cell.dataset.status;
                if (status === 'preferred') preferred++;
                else if (status === 'available') available++;
            });

            document.getElementById('preferredCount').textContent = preferred;
            document.getElementById('availableCount').textContent = available;
            document.getElementById('unavailableCount').textContent = 
                parseInt(document.getElementById('totalSlots').textContent) - preferred - available;
        }

        function saveAvailability() {
            // Preparar dados
            const slots = [];
            selectedSlots.forEach((preference, key) => {
                const [weekday, start, end] = key.split('-');
                slots.push({
                    weekday: parseInt(weekday),
                    starts_at: start,
                    ends_at: end,
                    preference: preference
                });
            });

            fetch(formAction, {
                method: 'PUT',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    availability_slots: slots
                })
            })
            .then(async response => {
                if (!response.ok) {
                    const error = await response.text();
                    console.error('Erro do servidor:', error);
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Mostrar toast de sucesso
                    showNotification('✓ Disponibilidade salva automaticamente!', 'success');
                    // Renderizar detalhamento
                    renderDetalhamento();
                } else {
                    showNotification('Erro ao salvar: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showNotification('❌ Erro ao salvar: ' + error.message, 'error');
            });
        }

        function renderDetalhamento() {
            console.log('renderDetalhamento() chamada');
            const container = document.getElementById('detalhamentoContainer');
            const tbody = document.getElementById('detalhamentoContent');
            const footer = document.getElementById('detalhamentoFooter');
            
            console.log('selectedSlots.size:', selectedSlots.size);
            console.log('selectedSlots:', selectedSlots);
            
            if (selectedSlots.size === 0) {
                console.log('Nenhum slot selecionado, escondendo container');
                container.style.display = 'none';
                footer.style.display = 'none';
                return;
            }

            const dayNames = {
                1: 'Segunda-feira',
                2: 'Terça-feira',
                3: 'Quarta-feira',
                4: 'Quinta-feira',
                5: 'Sexta-feira',
                6: 'Sábado'
            };

            const preferenceLabel = {
                'preferred': '✓ Preferido',
                'available': '◐ Disponível'
            };

            const preferenceBadge = {
                'preferred': '<span class="badge bg-success">✓ Preferido</span>',
                'available': '<span class="badge bg-info">◐ Disponível</span>'
            };

            // Montar array de slots ordenado
            const slots = [];
            selectedSlots.forEach((preference, key) => {
                const [weekday, start, end] = key.split('-');
                slots.push({
                    weekday: parseInt(weekday),
                    day: dayNames[weekday],
                    start: start,
                    end: end,
                    horario: `${start} - ${end}`,
                    preference: preference,
                    label: preferenceLabel[preference],
                    badge: preferenceBadge[preference],
                    key: key
                });
            });

            // Ordenar por weekday e horário
            slots.sort((a, b) => {
                if (a.weekday !== b.weekday) return a.weekday - b.weekday;
                return a.start.localeCompare(b.start);
            });

            // Montar linhas da tabela
            let html = '';
            slots.forEach((slot, index) => {
                const now = new Date();
                const registeredAt = now.toLocaleDateString('pt-BR') + ' ' + now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
                
                html += `
                    <tr data-slot-key="${slot.key}">
                        <td><strong>${slot.day}</strong></td>
                        <td>${slot.horario}</td>
                        <td>${slot.badge}</td>
                        <td><small class="text-muted">Registrado em ${registeredAt}</small></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary" onclick="editarPreferencia('${slot.key}', '${slot.horario}', '${slot.preference}')">
                                ✏️ Editar
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="excluirHorario('${slot.key}')">
                                🗑️ Excluir
                            </button>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
            container.style.display = 'block';

            // Atualizar footer com resumo
            let preferredCount = 0;
            let availableCount = 0;

            selectedSlots.forEach((preference) => {
                if (preference === 'preferred') preferredCount++;
                else if (preference === 'available') availableCount++;
            });

            const totalSlots = 90; // 15 horários × 6 dias
            const unavailableCount = totalSlots - preferredCount - availableCount;

            document.getElementById('footerTotal').textContent = selectedSlots.size;
            document.getElementById('footerPreferred').textContent = preferredCount;
            document.getElementById('footerAvailable').textContent = availableCount;
            document.getElementById('footerUnavailable').textContent = unavailableCount;

            footer.style.display = 'table-footer-group';
        }

        function editarPreferencia(slotKey, horario, currentPreference) {
            // Armazenar dados no modal
            window.currentEditSlot = slotKey;
            
            document.getElementById('editSlotInfo').textContent = horario;
            
            // Marcar a preferência atual
            document.getElementById('pref_preferred').checked = currentPreference === 'preferred';
            document.getElementById('pref_available').checked = currentPreference === 'available';
            
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('editPreferenceModal'));
            modal.show();
        }

        function excluirHorario(slotKey) {
            if (confirm('Tem certeza que deseja excluir este horário?')) {
                // Remover do Map
                selectedSlots.delete(slotKey);
                
                // Atualizar resumo
                updateSummary();
                
                // Atualizar detalhamento
                renderDetalhamento();
                
                // Atualizar célula na tabela
                const [weekday, start, end] = slotKey.split('-');
                const cell = document.querySelector(`.availability-cell[data-weekday="${weekday}"][data-start="${start}"][data-end="${end}"]`);
                if (cell) {
                    cell.className = 'availability-cell bg-light text-muted';
                    cell.innerHTML = '<small>✗ Indisponível</small>';
                    cell.dataset.status = 'unavailable';
                }
                
                // Agendar auto-save
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    saveAvailability();
                }, 1000);
                
                showNotification('✓ Horário excluído!', 'success');
            }
        }

        function showNotification(message, type) {
            // Remover notificação anterior se existir
            const existing = document.getElementById('notification-toast');
            if (existing) existing.remove();

            const toast = document.createElement('div');
            toast.id = 'notification-toast';
            toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
            toast.innerHTML = `${message}`;
            
            document.body.appendChild(toast);

            // Remover após 3 segundos se sucesso
            if (type === 'success') {
                setTimeout(() => toast.remove(), 3000);
            }
        }
    </script>
    @endif
