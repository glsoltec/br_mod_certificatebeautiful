# Plano de correções  br_mod_certificatebeautiful

## Escopo

Este repositório controla a emissão, acesso e entrega dos certificados.

As correções devem ser aplicadas uma por vez, com teste entre cada etapa.

## Etapa 1  Integração com auditoria

Adicionar chamadas ao plugin `local_certificatesign` para registrar:

- visualização;
- download;
- acesso pelo Moodle App com token;
- tentativa bloqueada por assinatura pendente.

Regras:

- registrar somente após validar a autorização;
- usuário comum acessa somente o próprio certificado;
- gestores devem usar capability adequada;
- nunca registrar token completo;
- não interromper a entrega por falha de auditoria;
- limitar IP, user-agent e ação;
- registrar curso, atividade, usuário e emissão.

Ações permitidas:

- `view`
- `download`
- `token_view`
- `pending`

Arquivos principais:

- `view-pdf.php`
- `view.php`
- `classes/event/certificatebeautiful_course_module_viewed.php`
- `classes/privacy/provider.php`

## Etapa 2  Remover notificação prematura

A notificação existente em `classes/automation.php` ocorre durante a geração.

Ela não deve ser tratada como confirmação de liberação enquanto a assinatura digital estiver pendente.

O módulo deve:

- manter a geração do PDF;
- aguardar o plugin de assinatura;
- liberar o arquivo somente após confirmação;
- deixar o envio de e-mail para `local_certificatesign`.

## Etapa 3  Bloqueio obrigatório

Quando a assinatura digital for requisito do ambiente:

- plugin de assinatura ausente não deve liberar o arquivo;
- plugin desativado não deve liberar o arquivo;
- emissão sem registro de assinatura deve retornar estado pendente;
- PDF assinado inexistente deve permanecer bloqueado.

Não expor detalhes internos de configuração ao usuário.

## Segurança do acesso mobile

- validar o token com o serviço externo Moodle correspondente;
- não aceitar apenas a existência textual do token;
- manter autorização do usuário após login por token;
- não usar URLs públicas permanentes;
- não incluir senha, PFX ou token em logs;
- manter `send_stored_file()` para entrega do arquivo;
- testar visualização e download no Moodle App.

## Privacidade

Atualizar o provider de privacidade para declarar dados de acesso e auditoria quando forem consumidos pelo módulo.

## Validação

Executar:

```bash
php -l view.php
php -l view-pdf.php
php -l classes/event/certificatebeautiful_course_module_viewed.php
php -l classes/privacy/provider.php
git diff --check
```

Testar:

1. Usuário proprietário visualiza.
2. Usuário proprietário baixa.
3. Moodle App visualiza.
4. Moodle App baixa.
5. Outro usuário é bloqueado.
6. Certificado pendente não é entregue.
7. Auditoria registra curso e emissão.

## Commit sugerido

```
Integrate certificate access audit
```
