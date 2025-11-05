document.querySelectorAll('.btn-approve').forEach(btn => {
    btn.addEventListener('click', async function() {
        const id = this.getAttribute('data-id');
        
        if (!confirm('Deseja aprovar este agendamento?')) {
            return;
        }
        
        try {
            const response = await fetch(`api/admin.php?action=aprovar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${id}`
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('Agendamento aprovado com sucesso!');
                location.reload();
            } else {
                alert('Erro: ' + (result.message || 'Erro ao aprovar agendamento'));
            }
        } catch (error) {
            alert('Erro ao processar solicitação. Tente novamente.');
        }
    });
});

document.querySelectorAll('.btn-reject').forEach(btn => {
    btn.addEventListener('click', async function() {
        const id = this.getAttribute('data-id');
        
        if (!confirm('Deseja rejeitar este agendamento?')) {
            return;
        }
        
        try {
            const response = await fetch(`api/admin.php?action=rejeitar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${id}`
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('Agendamento rejeitado.');
                location.reload();
            } else {
                alert('Erro: ' + (result.message || 'Erro ao rejeitar agendamento'));
            }
        } catch (error) {
            alert('Erro ao processar solicitação. Tente novamente.');
        }
    });
});
