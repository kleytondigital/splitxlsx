
# List Standardizer — Next.js + Laravel + Postgres

Pipeline completo para transformar planilhas de contatos em listas padronizadas de 100 itens que podem ser importadas no app de disparo de mensagens.

## Stack
- `backend/` — Laravel 12 com PhpSpreadsheet, testes Feature e Dockerfile multi-stage.
- `frontend/` — Next.js 14 + TypeScript, testes com Testing Library/Jest.
- `docker-compose.yml` — Backend, Frontend e Postgres com volumes persistentes.
- `README_DEPLOY.md` — Guia rápido para EasyPanel.

## Pré-requisitos
- PHP 8.2 + Composer
- Node.js 18 + npm
- Docker / Docker Compose (opcional para execução containerizada)

## Configuração rápida
1. `cp backend/.env.example backend/.env` e ajuste credenciais.
2. Gere a APP_KEY (local ou dentro do container):
   ```bash
   cd backend
   composer install
   php artisan key:generate
   ```
3. Configure o frontend:
   ```bash
   cd frontend
   npm install
   cp .env.example .env.local # ajuste NEXT_PUBLIC_BACKEND_URL se necessário
   ```

## Executando localmente

### Docker Compose
```bash
docker compose up --build
```
- Frontend: http://localhost:3000
- Backend API: http://localhost:8000/api/upload
- Postgres com dados persistidos em `db_data`.

### Manualmente
- Backend: `php artisan serve --host=0.0.0.0 --port=8000`
- Frontend: `npm run dev --prefix frontend`

## Testes
- Backend: `cd backend && php artisan test`
- Frontend: `cd frontend && npm test`

## CI/CD
Há um workflow em `.github/workflows/deploy.yml` que:
1. Executa testes do Laravel e do Next.js a cada push/PR.
2. (Opcional) Constrói e publica imagens Docker quando `EASYPANEL_*` secrets estão configurados e o push acontece na `main`.

Configure no repositório os seguintes secrets para liberar o deploy automático:
- `EASYPANEL_REGISTRY`
- `EASYPANEL_USERNAME`
- `EASYPANEL_PASSWORD`

## Dicas
- Atualize `backend/.env` com `APP_KEY`, host do banco e demais secrets antes de subir os containers.
- Para ambientes produtivos, troque `NEXT_PUBLIC_BACKEND_URL` para o domínio público do backend ou use o serviço interno (`http://backend:8000`) quando estiver dentro da mesma rede Docker.
- Considere agendar `php artisan schedule:run` (via cron) se futuramente incluir tarefas assíncronas.
