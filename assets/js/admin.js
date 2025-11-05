document.querySelectorAll('.btn-approve').forEach(btn => {
    btn.addEventListener('click', async function() {
        const id = this.getAttribute('data-id');
        
        if (!confirm('Deseja aprovar este agendamento?')) {
            return;
        }
        
        try {
            const response = await fetch(`../api/admin.php?action=aprovar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${id}`
            });
            
            const result = await response.json();
            
            if (result.success) {
                let mensagem = 'Agendamento aprovado com sucesso!\n\n';
                let temErros = false;
                
                if (result.google_calendar) {
                    if (result.google_calendar.sucesso) {
                        mensagem += 'Evento criado no Google Calendar\n';
                    } else {
                        mensagem += 'Erro ao criar evento no Google Calendar:\n';
                        if (result.google_calendar.erros && result.google_calendar.erros.length > 0) {
                            mensagem += result.google_calendar.erros.join('\n') + '\n';
                        } else {
                            mensagem += 'Erro desconhecido\n';
                        }
                        temErros = true;
                    }
                }
                
                if (result.email) {
                    if (result.email.enviado) {
                        mensagem += 'Email enviado com sucesso\n';
                    } else {
                        mensagem += 'Erro ao enviar email:\n';
                        mensagem += (result.email.erro || 'Erro desconhecido') + '\n';
                        temErros = true;
                    }
                }
                
                console.log('Resultado da aprovação:', result);
                if (temErros) {
                    console.error('Erros encontrados:', {
                        google_calendar: result.google_calendar,
                        email: result.email
                    });
                }
                
                alert(mensagem);
                
                setTimeout(() => {
                    location.reload();
                }, temErros ? 3000 : 1000);
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
        
        const motivo = prompt('Deseja rejeitar este agendamento?\n\nPor favor, informe o motivo da rejeição:');
        
        if (motivo === null) {
            return;
        }
        
        if (motivo.trim() === '') {
            alert('Por favor, informe o motivo da rejeição.');
            return;
        }
        
        try {
            const response = await fetch(`../api/admin.php?action=rejeitar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${id}&motivo=${encodeURIComponent(motivo)}`
            });
            
            const result = await response.json();
            
            if (result.success) {
                let mensagem = 'Agendamento rejeitado!\n\n';
                
                if (result.email) {
                    if (result.email.enviado) {
                        mensagem += 'Email enviado com sucesso\n';
                    } else {
                        mensagem += 'Erro ao enviar email:\n';
                        mensagem += (result.email.erro || 'Erro desconhecido') + '\n';
                    }
                }
                
                alert(mensagem);
                location.reload();
            } else {
                alert('Erro: ' + (result.message || 'Erro ao rejeitar agendamento'));
            }
        } catch (error) {
            alert('Erro ao processar solicitação. Tente novamente.');
        }
    });
});

document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', async function() {
        const id = this.getAttribute('data-id');
        
        if (!confirm('ATENCAO: Deseja excluir permanentemente este agendamento?\n\nO evento também será removido do Google Calendar se existir.\n\nEsta ação não pode ser desfeita!')) {
            return;
        }
        
        try {
            const response = await fetch(`../api/admin.php?action=excluir`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${id}`
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('Agendamento excluído com sucesso!');
                location.reload();
            } else {
                alert('Erro: ' + (result.message || 'Erro ao excluir agendamento'));
            }
        } catch (error) {
            alert('Erro ao processar solicitação. Tente novamente.');
        }
    });
});

document.getElementById('refresh-status')?.addEventListener('click', async function() {
    try {
        const response = await fetch('../api/admin.php?action=status-google');
        const result = await response.json();
        
        if (result.success) {
            location.reload();
        }
    } catch (error) {
        alert('Erro ao atualizar status do Google Calendar.');
    }
});
