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
                const horario = evento.horario_entrada ? evento.horario_entrada.substring(0, 5) : '';
                html += `<div class="calendar-event" title="${evento.espaco} - ${evento.nome_servidor}" style="margin-top: 4px; font-size: 0.75rem;">`;
                html += `${horario} - ${evento.espaco}`;
                html += `</div>`;
            });
        }
        
        html += '</div>';
    }
    
    html += '</div>';
    calendarioDiv.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', function() {
    carregarCalendario();
});
