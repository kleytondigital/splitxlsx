
import { ChangeEvent, useCallback, useMemo, useState } from 'react';

export default function Home() {
  const [file, setFile] = useState<File | null>(null);
  const [loading, setLoading] = useState(false);

  const backendUrl = useMemo(
    () => process.env.NEXT_PUBLIC_BACKEND_URL || 'https://n8n-mpc.h3ag2x.easypanel.host',
    []
  );

  const handleFileChange = useCallback((event: ChangeEvent<HTMLInputElement>) => {
    setFile(event.target.files?.[0] ?? null);
  }, []);

  const upload = useCallback(async () => {
    if (!file) {
      alert('Selecione um arquivo');
      return;
    }

    setLoading(true);
    const fd = new FormData();
    fd.append('file', file);

    try {
      const res = await fetch(`${backendUrl}/api/upload`, {
        method: 'POST',
        body: fd,
      });
      if (!res.ok) {
        const err = await res.json();
        alert('Erro: ' + (err.error || res.statusText));
        return;
      }
      const blob = await res.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'listas_padronizadas.zip';
      a.click();
    } catch (e: any) {
      alert('Erro ao conectar com backend: ' + e.message);
    } finally {
      setLoading(false);
    }
  }, [backendUrl, file]);

  return (
    <div style={{ maxWidth: 800, margin: '40px auto', padding: 20, fontFamily: 'Inter, system-ui' }}>
      <h1>List Standardizer</h1>
      <p>
        Faça upload de um arquivo XLSX/CSV com contatos. O backend retornará um ZIP com listas padronizadas (100 itens por arquivo).
      </p>

      <label htmlFor="contacts-file" style={{ display: 'block', marginBottom: 8 }}>
        Arquivo de contatos
      </label>
      <input
        id="contacts-file"
        data-testid="file-input"
        type="file"
        accept=".xlsx,.csv"
        onChange={handleFileChange}
      />
      <div style={{ marginTop: 12 }}>
        <button onClick={upload} disabled={loading} style={{ padding: '8px 16px' }} aria-busy={loading}>
          {loading ? 'Processando...' : 'Processar lista'}
        </button>
      </div>
    </div>
  );
}
