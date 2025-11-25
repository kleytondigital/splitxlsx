
# Deploy no EasyPanel

1. Faça fork/clone deste repositório e configure os secrets `EASYPANEL_*` (caso queira usar o workflow de deploy).
2. Gere a APP_KEY localmente (`php artisan key:generate`) e salve em `backend/.env`. Faça commit apenas do `.env.example`.
3. **Opção A — Imagem única (recomendada para EasyPanel simples):**
   - Selecione o Dockerfile da raiz.
   - Exponha as portas 8000 (API) e 3000 (frontend) ou coloque um proxy diante disso.
   - Informe as variáveis de ambiente do backend (APP_KEY, DB_*, etc.) e também `NEXT_PUBLIC_BACKEND_URL` (por exemplo `https://seu-dominio/api`).
4. **Opção B — Serviços separados (maior isolamento):**
   - **Postgres 15**: defina `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD`.
   - **Backend**: build `./backend`, porta interna 8000, passe as variáveis do banco e `APP_KEY`.
   - **Frontend**: build `./frontend`, porta interna 3000, defina `NEXT_PUBLIC_BACKEND_URL` apontando para o backend.
5. Configure volumes persistentes:
   - Backend: monte `backend/storage` e `backend/bootstrap/cache`.
   - Postgres: monte `/var/lib/postgresql/data`.
6. Execute o workflow `Deploy` (ou faça build manual). Após o build, valide:
   - `php artisan test` (backend) e `npm run build` (frontend) devem passar.
   - O frontend consegue atingir `NEXT_PUBLIC_BACKEND_URL/api/upload`.

> Dica: use domínios diferentes (ex.: api.meudominio.com e app.meudominio.com) e configure HTTPS direto no EasyPanel ou através de um proxy reverso externo.
