document.querySelectorAll('input[name="espaco"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const equipamentosGroup = document.getElementById('equipamentos-group');
        if (this.value === 'Laboratório IFMaker') {
            equipamentosGroup.style.display = 'block';
        } else {
            equipamentosGroup.style.display = 'none';
        }
    });
});

async function verificarHorarioLotado(data, horarioEntrada, horarioSaida, espaco) {
    try {
        const response = await fetch(`api/agendamentos.php?data_inicio=${data}&data_fim=${data}&status=aprovado`);
        const result = await response.json();
        
        if (!result.success || !result.data) return false;
        
        const limites = {
            'Laboratório IFMaker': 3,
            'CoWorking': 8,
            'Sala de Reunião': 1
        };
        
        const limite = limites[espaco] || 0;
        const agendamentos = result.data.filter(a => {
            return a.espaco === espaco && 
                   a.data_utilizacao === data &&
                   (
                       (a.horario_entrada <= horarioEntrada && a.horario_saida > horarioEntrada) ||
                       (a.horario_entrada < horarioSaida && a.horario_saida >= horarioSaida) ||
                       (a.horario_entrada >= horarioEntrada && a.horario_saida <= horarioSaida)
                   );
        });
        
        const ocupaTodoEspaco = agendamentos.some(a => a.ocupa_todo_espaco == 1);
        if (ocupaTodoEspaco) return true;
        
        return agendamentos.length >= limite;
    } catch (error) {
        console.error('Erro ao verificar horário:', error);
        return false;
    }
}

document.getElementById('data_utilizacao')?.addEventListener('change', atualizarStatusHorario);
document.getElementById('horario_entrada')?.addEventListener('change', atualizarStatusHorario);
document.getElementById('horario_saida')?.addEventListener('change', atualizarStatusHorario);
document.querySelectorAll('input[name="espaco"]').forEach(radio => {
    radio.addEventListener('change', atualizarStatusHorario);
});

async function atualizarStatusHorario() {
    const data = document.getElementById('data_utilizacao')?.value;
    const horarioEntrada = document.getElementById('horario_entrada')?.value;
    const horarioSaida = document.getElementById('horario_saida')?.value;
    const espaco = document.querySelector('input[name="espaco"]:checked')?.value;
    
    let statusDiv = document.getElementById('status-horario');
    if (!statusDiv) {
        statusDiv = document.createElement('div');
        statusDiv.id = 'status-horario';
        statusDiv.className = 'mt-2';
        const formGroup = document.getElementById('horario_saida')?.closest('.form-group');
        if (formGroup) {
            formGroup.appendChild(statusDiv);
        }
    }
    
    if (!data || !horarioEntrada || !horarioSaida || !espaco) {
        statusDiv.innerHTML = '';
        return;
    }
    
    if (horarioEntrada >= horarioSaida) {
        statusDiv.innerHTML = '<div class="alert alert-warning" style="padding: 0.5rem; margin: 0;">Horário de entrada deve ser anterior ao horário de saída</div>';
        return;
    }
    
    const lotado = await verificarHorarioLotado(data, horarioEntrada, horarioSaida, espaco);
    
    if (lotado) {
        statusDiv.innerHTML = '<div class="alert alert-danger" style="padding: 0.5rem; margin: 0;">Este horário está LOTADO para este espaço. Escolha outro horário ou espaço.</div>';
    } else {
        statusDiv.innerHTML = '<div class="alert alert-success" style="padding: 0.5rem; margin: 0;">Horário disponível</div>';
    }
}

document.getElementById('formAgendamento').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = {};
    
    for (let [key, value] of formData.entries()) {
        if (key === 'equipamentos[]') {
            if (!data.equipamentos) data.equipamentos = [];
            data.equipamentos.push(value);
        } else {
            data[key] = value;
        }
    }
    
    if (data.equipamentos) {
        data.equipamentos = data.equipamentos.join(', ');
    }
    if (data.equipamentos_outro) {
        data.equipamentos = (data.equipamentos || '') + (data.equipamentos ? ', ' : '') + data.equipamentos_outro;
    }
    delete data.equipamentos_outro;
    
    const lotado = await verificarHorarioLotado(data.data_utilizacao, data.horario_entrada, data.horario_saida, data.espaco);
    if (lotado) {
        document.getElementById('mensagem').innerHTML = '<div class="alert alert-danger">Este horário está lotado. Por favor, escolha outro horário ou espaço.</div>';
        return;
    }
    
    try {
        const response = await fetch('api/agendamentos.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        const mensagemDiv = document.getElementById('mensagem');
        if (result.success) {
            mensagemDiv.innerHTML = '<div class="alert alert-success">Agendamento criado com sucesso! Aguarde aprovação.</div>';
            document.getElementById('formAgendamento').reset();
            document.getElementById('equipamentos-group').style.display = 'none';
            document.getElementById('status-horario').innerHTML = '';
            carregarCalendario();
        } else {
            mensagemDiv.innerHTML = `<div class="alert alert-danger">${result.message || 'Erro ao criar agendamento'}</div>`;
        }
        
        const cardBody = document.querySelector('.form-scrollable');
        if (cardBody) {
            cardBody.scrollTop = cardBody.scrollHeight;
        }
    } catch (error) {
        document.getElementById('mensagem').innerHTML = '<div class="alert alert-danger">Erro ao processar solicitação. Tente novamente.</div>';
    }
});

async function carregarCalendario() {
    const hoje = new Date();
    const primeiroDia = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
    const ultimoDia = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0);
    
    const dataInicio = primeiroDia.toISOString().split('T')[0];
    const dataFim = ultimoDia.toISOString().split('T')[0];
    
    try {
        const response = await fetch(`api/agendamentos.php?data_inicio=${dataInicio}&data_fim=${dataFim}&status=aprovado`);
        
        if (!response.ok) {
            throw new Error('Erro ao buscar agendamentos');
        }
        
        const result = await response.json();
        
        if (result.success) {
            renderizarCalendario(result.data || [], hoje.getMonth(), hoje.getFullYear());
        } else {
            renderizarCalendario([], hoje.getMonth(), hoje.getFullYear());
        }
    } catch (error) {
        console.error('Erro ao carregar calendário:', error);
        renderizarCalendario([], hoje.getMonth(), hoje.getFullYear());
    }
}

function renderizarCalendario(agendamentos, mes, ano) {
    const calendarioDiv = document.getElementById('calendario');
    
    if (!calendarioDiv) {
        console.error('Elemento calendario não encontrado!');
        return;
    }
    
    const meses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
                   'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    
    const primeiroDia = new Date(ano, mes, 1);
    const ultimoDia = new Date(ano, mes + 1, 0);
    const diasNoMes = ultimoDia.getDate();
    const diaSemanaInicio = primeiroDia.getDay();
    
    if (!Array.isArray(agendamentos)) {
        agendamentos = [];
    }
    
    let html = `<h3>${meses[mes]} ${ano}</h3>`;
    html += '<div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; margin-top: 1rem;">';
    
    const diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    diasSemana.forEach(dia => {
        html += `<div style="font-weight: bold; text-align: center; padding: 0.5rem; background: #f3f4f6; border-radius: 4px;">${dia}</div>`;
    });
    
    for (let i = 0; i < diaSemanaInicio; i++) {
        html += '<div></div>';
    }
    
    const hoje = new Date();
    const hojeStr = hoje.getDate();
    const hojeMes = hoje.getMonth();
    const hojeAno = hoje.getFullYear();
    
    for (let dia = 1; dia <= diasNoMes; dia++) {
        const dataStr = `${ano}-${String(mes + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
        const eventosDoDia = agendamentos.filter(a => a.data_utilizacao === dataStr);
        const isHoje = (dia === hojeStr && mes === hojeMes && ano === hojeAno);
        
        html += `<div class="calendar-day ${eventosDoDia.length > 0 ? 'has-events' : ''} ${isHoje ? 'today' : ''}" style="min-height: 80px; padding: 0.5rem; border: 1px solid #e5e7eb; border-radius: 4px; background: ${isHoje ? '#eff6ff' : 'white'};">`;
        html += `<strong style="color: ${isHoje ? '#2563eb' : 'inherit'};">${dia}</strong>`;
        
        if (eventosDoDia.length > 0) {
            eventosDoDia.forEach(evento => {
                const espacoCor = getEspacoCor(evento.espaco);
                html += `<div class="calendar-event" 
                    data-event-id="${evento.id}" 
                    data-event-data='${JSON.stringify(evento).replace(/'/g, "&#39;")}'
                    title="Clique para ver detalhes - ${evento.espaco} - ${evento.nome_servidor}" 
                    style="margin-top: 4px; font-size: 0.75rem; background: ${espacoCor}; cursor: pointer;">
                    ${evento.nome_servidor || 'Sem nome'}
                </div>`;
            });
        }
        
        html += '</div>';
    }
    
    html += '</div>';
    calendarioDiv.innerHTML = html;
    
    document.querySelectorAll('.calendar-event').forEach(evento => {
        evento.addEventListener('click', function() {
            const eventData = JSON.parse(this.getAttribute('data-event-data'));
            mostrarDetalhesEvento(eventData);
        });
    });
}

function getEspacoCor(espaco) {
    const cores = {
        'Laboratório IFMaker': 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        'CoWorking': 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
        'Sala de Reunião': 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)'
    };
    return cores[espaco] || 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)';
}

function mostrarDetalhesEvento(evento) {
    let modal = document.getElementById('modal-detalhes');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'modal-detalhes';
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Detalhes do Agendamento</h2>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body" id="modal-body">
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        
        modal.querySelector('.modal-close').addEventListener('click', () => {
            modal.style.display = 'none';
        });
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    }
    
    const modalBody = document.getElementById('modal-body');
    const horarioEntrada = evento.horario_entrada ? evento.horario_entrada.substring(0, 5) : '';
    const horarioSaida = evento.horario_saida ? evento.horario_saida.substring(0, 5) : '';
    
    modalBody.innerHTML = `
        <div class="detalhes-evento">
            <div class="detalhe-item">
                <strong>Nome:</strong>
                <span>${evento.nome_servidor || 'N/A'}</span>
            </div>
            <div class="detalhe-item">
                <strong>Email:</strong>
                <span>${evento.email || 'N/A'}</span>
            </div>
            <div class="detalhe-item">
                <strong>Pessoas Responsáveis:</strong>
                <span>${evento.pessoas_responsaveis || 'N/A'}</span>
            </div>
            <div class="detalhe-item">
                <strong>Data:</strong>
                <span>${formatarDataBR(evento.data_utilizacao)}</span>
            </div>
            <div class="detalhe-item">
                <strong>Horário:</strong>
                <span>${horarioEntrada} às ${horarioSaida}</span>
            </div>
            <div class="detalhe-item">
                <strong>Espaço:</strong>
                <span class="espaco-badge" style="background: ${getEspacoCor(evento.espaco)}">${evento.espaco || 'N/A'}</span>
            </div>
            ${evento.equipamentos ? `
            <div class="detalhe-item">
                <strong>Equipamentos:</strong>
                <span>${evento.equipamentos}</span>
            </div>
            ` : ''}
            <div class="detalhe-item">
                <strong>Status:</strong>
                <span class="status-badge status-${evento.status}">${getStatusNome(evento.status)}</span>
            </div>
        </div>
    `;
    
    modal.style.display = 'flex';
}

function formatarDataBR(data) {
    if (!data) return 'N/A';
    const partes = data.split('-');
    if (partes.length === 3) {
        return `${partes[2]}/${partes[1]}/${partes[0]}`;
    }
    return data;
}

function getStatusNome(status) {
    const statusMap = {
        'pendente': 'Pendente',
        'aprovado': 'Aprovado',
        'rejeitado': 'Rejeitado'
    };
    return statusMap[status] || status;
}

document.addEventListener('DOMContentLoaded', function() {
    carregarCalendario();
});
