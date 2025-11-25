
# Deploy no EasyPanel

1. Faça fork/clone deste repositório e configure os secrets `EASYPANEL_*` (caso queira usar o workflow de deploy).
2. Gere a APP_KEY localmente (`php artisan key:generate`) e salve em `backend/.env`. Faça commit apenas do `.env.example`.
3. No EasyPanel crie:
   - **Postgres 15**: defina `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD` (os mesmos usados no `.env`).
   - **Backend**: build `./backend`, porta interna 8000, passe variáveis `APP_KEY`, `DB_*`, `APP_URL`, `APP_ENV=production`.
   - **Frontend**: build `./frontend`, porta interna 3000, defina `NEXT_PUBLIC_BACKEND_URL` apontando para o domínio/serviço do backend.
4. Configure volumes persistentes:
   - Backend: monte `storage` e `bootstrap/cache`.
   - Postgres: monte `/var/lib/postgresql/data`.
5. Execute o workflow `Deploy` (ou faça build manual). Após o build, valide:
   - `php artisan test` (backend) e `npm run build` (frontend) devem passar.
   - O frontend consegue atingir `NEXT_PUBLIC_BACKEND_URL/api/upload`.

> Dica: use domínios diferentes (ex.: api.meudominio.com e app.meudominio.com) e configure HTTPS direto no EasyPanel ou através de um proxy reverso externo.
