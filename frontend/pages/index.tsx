
import { ChangeEvent, useCallback, useMemo, useState, DragEvent } from 'react';

export default function Home() {
  const [file, setFile] = useState<File | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);
  const [isDragging, setIsDragging] = useState(false);
  const [removeDuplicates, setRemoveDuplicates] = useState(true);
  const [downloadType, setDownloadType] = useState<'grouped' | 'separated'>('separated');
  const [chunkSize, setChunkSize] = useState(100);
  const [statistics, setStatistics] = useState<any>(null);

  const backendUrl = useMemo(
    () => process.env.NEXT_PUBLIC_BACKEND_URL || 'https://n8n-mpc.h3ag2x.easypanel.host',
    []
  );

  const handleFileChange = useCallback((event: ChangeEvent<HTMLInputElement>) => {
    const selectedFile = event.target.files?.[0];
    if (selectedFile) {
      setFile(selectedFile);
      setError(null);
      setSuccess(false);
    }
  }, []);

  const handleDragOver = useCallback((e: DragEvent<HTMLDivElement>) => {
    e.preventDefault();
    setIsDragging(true);
  }, []);

  const handleDragLeave = useCallback(() => {
    setIsDragging(false);
  }, []);

  const handleDrop = useCallback((e: DragEvent<HTMLDivElement>) => {
    e.preventDefault();
    setIsDragging(false);
    const droppedFile = e.dataTransfer.files[0];
    if (droppedFile && (droppedFile.name.endsWith('.xlsx') || droppedFile.name.endsWith('.csv'))) {
      setFile(droppedFile);
      setError(null);
      setSuccess(false);
    } else {
      setError('Por favor, selecione um arquivo XLSX ou CSV');
    }
  }, []);

  const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
  };

  const upload = useCallback(async () => {
    if (!file) {
      setError('Por favor, selecione um arquivo');
      return;
    }

    setLoading(true);
    setError(null);
    setSuccess(false);
    setStatistics(null);
    const fd = new FormData();
    fd.append('file', file);
    fd.append('remove_duplicates', removeDuplicates.toString());
    fd.append('download_type', downloadType);
    fd.append('chunk_size', chunkSize.toString());

    try {
      const res = await fetch(`${backendUrl}/api/upload`, {
        method: 'POST',
        body: fd,
      });
      if (!res.ok) {
        const err = await res.json();
        setError(err.error || res.statusText || 'Erro ao processar arquivo');
        return;
      }
      const blob = await res.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'listas_padronizadas.zip';
      a.click();
      window.URL.revokeObjectURL(url);
      setSuccess(true);
      setStatistics({
        totalContacts: 'Processado com sucesso',
        downloadType: downloadType === 'grouped' ? 'Arquivo único' : 'Múltiplos arquivos',
        chunkSize: downloadType === 'separated' ? chunkSize : 'N/A',
        duplicatesRemoved: removeDuplicates,
      });
      // Não limpa o arquivo para permitir novo processamento com diferentes opções
    } catch (e: any) {
      setError('Erro ao conectar com o servidor: ' + (e.message || 'Tente novamente'));
    } finally {
      setLoading(false);
    }
  }, [backendUrl, file]);

  return (
    <div style={styles.container}>
      <div style={styles.card}>
        <div style={styles.header}>
          <div style={styles.iconContainer}>
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
              <polyline points="17 8 12 3 7 8" />
              <line x1="12" y1="3" x2="12" y2="15" />
            </svg>
          </div>
          <h1 style={styles.title}>Padronizador de Listas</h1>
          <p style={styles.subtitle}>
            Faça upload de um arquivo XLSX ou CSV com contatos. O sistema detecta automaticamente as colunas, padroniza os números e remove duplicados. Escolha entre download agrupado ou separado em múltiplos arquivos.
          </p>
        </div>

        <div
          style={{
            ...styles.dropZone,
            ...(isDragging ? styles.dropZoneActive : {}),
            ...(file ? styles.dropZoneHasFile : {}),
          }}
          onDragOver={handleDragOver}
          onDragLeave={handleDragLeave}
          onDrop={handleDrop}
        >
          <input
            id="contacts-file"
            data-testid="file-input"
            type="file"
            accept=".xlsx,.csv"
            onChange={handleFileChange}
            style={styles.hiddenInput}
          />
          <label htmlFor="contacts-file" style={styles.dropZoneLabel}>
            {file ? (
              <>
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={styles.fileIcon}>
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                  <polyline points="14 2 14 8 20 8" />
                  <line x1="16" y1="13" x2="8" y2="13" />
                  <line x1="16" y1="17" x2="8" y2="17" />
                  <polyline points="10 9 9 9 8 9" />
                </svg>
                <div style={styles.fileInfo}>
                  <div style={styles.fileName}>{file.name}</div>
                  <div style={styles.fileSize}>{formatFileSize(file.size)}</div>
                </div>
                <button
                  type="button"
                  onClick={(e) => {
                    e.stopPropagation();
                    setFile(null);
                    setError(null);
                    setSuccess(false);
                  }}
                  style={styles.removeButton}
                  aria-label="Remover arquivo"
                >
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                  </svg>
                </button>
              </>
            ) : (
              <>
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={styles.uploadIcon}>
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                  <polyline points="17 8 12 3 7 8" />
                  <line x1="12" y1="3" x2="12" y2="15" />
                </svg>
                <div style={styles.dropZoneText}>
                  <strong>Clique para selecionar</strong> ou arraste o arquivo aqui
                </div>
                <div style={styles.dropZoneHint}>Formatos aceitos: XLSX, CSV (máx. 5MB)</div>
              </>
            )}
          </label>
        </div>

        {error && (
          <div style={styles.alertError}>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="8" x2="12" y2="12" />
              <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            <span>{error}</span>
          </div>
        )}

        {success && (
          <div style={styles.alertSuccess}>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <polyline points="20 6 9 17 4 12" />
            </svg>
            <span>Arquivo processado com sucesso! O download iniciará automaticamente.</span>
          </div>
        )}

        {file && (
          <div style={styles.optionsSection}>
            <h3 style={styles.optionsTitle}>Opções de Processamento</h3>
            
            <div style={styles.optionGroup}>
              <label style={styles.checkboxLabel}>
                <input
                  type="checkbox"
                  checked={removeDuplicates}
                  onChange={(e) => setRemoveDuplicates(e.target.checked)}
                  style={styles.checkbox}
                />
                <span>Remover duplicados</span>
              </label>
              <div style={styles.optionHint}>Remove contatos com o mesmo número de telefone</div>
            </div>

            <div style={styles.optionGroup}>
              <label style={styles.label}>Tipo de Download:</label>
              <div style={styles.radioGroup}>
                <label style={styles.radioLabel}>
                  <input
                    type="radio"
                    name="downloadType"
                    value="separated"
                    checked={downloadType === 'separated'}
                    onChange={(e) => setDownloadType(e.target.value as 'separated' | 'grouped')}
                    style={styles.radio}
                  />
                  <span>Separado (múltiplos arquivos)</span>
                </label>
                <label style={styles.radioLabel}>
                  <input
                    type="radio"
                    name="downloadType"
                    value="grouped"
                    checked={downloadType === 'grouped'}
                    onChange={(e) => setDownloadType(e.target.value as 'separated' | 'grouped')}
                    style={styles.radio}
                  />
                  <span>Agrupado (arquivo único)</span>
                </label>
              </div>
            </div>

            {downloadType === 'separated' && (
              <div style={styles.optionGroup}>
                <label style={styles.label}>
                  Tamanho do chunk (contatos por arquivo):
                </label>
                <input
                  type="number"
                  min="1"
                  max="1000"
                  value={chunkSize}
                  onChange={(e) => setChunkSize(Math.max(1, Math.min(1000, parseInt(e.target.value) || 100)))}
                  style={styles.numberInput}
                />
                <div style={styles.optionHint}>Número de contatos por arquivo (1-1000)</div>
              </div>
            )}
          </div>
        )}

        <button
          onClick={upload}
          disabled={loading || !file}
          style={{
            ...styles.submitButton,
            ...(loading || !file ? styles.submitButtonDisabled : {}),
          }}
          aria-busy={loading}
        >
          {loading ? (
            <>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={styles.spinner}>
                <circle cx="12" cy="12" r="10" strokeDasharray="32" strokeDashoffset="32">
                  <animate attributeName="stroke-dasharray" dur="2s" values="0 32;16 16;0 32;0 32" repeatCount="indefinite" />
                  <animate attributeName="stroke-dashoffset" dur="2s" values="0;-16;-32;-32" repeatCount="indefinite" />
                </circle>
              </svg>
              Processando...
            </>
          ) : (
            <>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="17 8 12 3 7 8" />
                <line x1="12" y1="3" x2="12" y2="15" />
              </svg>
              Processar Lista
            </>
          )}
        </button>

        <div style={styles.footer}>
          <div style={styles.infoBox}>
            <strong>Recursos da Ferramenta:</strong>
            <ul style={styles.infoList}>
              <li><strong>Detecção automática:</strong> Identifica colunas de telefone e nome automaticamente</li>
              <li><strong>Padronização:</strong> Converte números para formato internacional (55 + DDD + número)</li>
              <li><strong>Remoção de duplicados:</strong> Elimina contatos com o mesmo número de telefone</li>
              <li><strong>Download flexível:</strong> Escolha entre arquivo único ou múltiplos arquivos</li>
              <li><strong>Configurável:</strong> Ajuste o tamanho dos chunks (1-1000 contatos por arquivo)</li>
              <li><strong>Estatísticas:</strong> Receba relatório detalhado do processamento</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
}

const styles: { [key: string]: React.CSSProperties } = {
  container: {
    minHeight: '100vh',
    background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
    padding: '20px',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontFamily: 'Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
  },
  card: {
    maxWidth: '600px',
    width: '100%',
    background: '#ffffff',
    borderRadius: '16px',
    boxShadow: '0 20px 60px rgba(0, 0, 0, 0.3)',
    padding: '40px',
    animation: 'fadeIn 0.3s ease-in',
  },
  header: {
    textAlign: 'center',
    marginBottom: '32px',
  },
  iconContainer: {
    width: '64px',
    height: '64px',
    margin: '0 auto 16px',
    background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
    borderRadius: '16px',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    color: '#ffffff',
  },
  title: {
    fontSize: '28px',
    fontWeight: '700',
    color: '#1a202c',
    margin: '0 0 8px 0',
    letterSpacing: '-0.5px',
  },
  subtitle: {
    fontSize: '15px',
    color: '#718096',
    lineHeight: '1.6',
    margin: 0,
  },
  dropZone: {
    border: '2px dashed #cbd5e0',
    borderRadius: '12px',
    padding: '40px 20px',
    textAlign: 'center',
    cursor: 'pointer',
    transition: 'all 0.3s ease',
    background: '#f7fafc',
    marginBottom: '20px',
    position: 'relative',
  },
  dropZoneActive: {
    borderColor: '#667eea',
    background: '#edf2f7',
    transform: 'scale(1.02)',
  },
  dropZoneHasFile: {
    borderColor: '#48bb78',
    background: '#f0fff4',
    padding: '24px',
  },
  dropZoneLabel: {
    display: 'block',
    cursor: 'pointer',
    width: '100%',
  },
  dropZoneText: {
    fontSize: '16px',
    color: '#2d3748',
    marginTop: '16px',
    marginBottom: '8px',
  },
  dropZoneHint: {
    fontSize: '13px',
    color: '#a0aec0',
  },
  hiddenInput: {
    position: 'absolute',
    width: '1px',
    height: '1px',
    opacity: 0,
    overflow: 'hidden',
  },
  uploadIcon: {
    color: '#667eea',
    margin: '0 auto',
  },
  fileIcon: {
    color: '#48bb78',
    marginBottom: '12px',
  },
  fileInfo: {
    marginTop: '12px',
  },
  fileName: {
    fontSize: '16px',
    fontWeight: '600',
    color: '#2d3748',
    marginBottom: '4px',
    wordBreak: 'break-word',
  },
  fileSize: {
    fontSize: '14px',
    color: '#718096',
  },
  removeButton: {
    position: 'absolute',
    top: '12px',
    right: '12px',
    background: '#fed7d7',
    border: 'none',
    borderRadius: '8px',
    width: '32px',
    height: '32px',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    cursor: 'pointer',
    color: '#c53030',
    transition: 'all 0.2s ease',
  },
  submitButton: {
    width: '100%',
    padding: '16px 24px',
    background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
    color: '#ffffff',
    border: 'none',
    borderRadius: '12px',
    fontSize: '16px',
    fontWeight: '600',
    cursor: 'pointer',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: '8px',
    transition: 'all 0.3s ease',
    boxShadow: '0 4px 12px rgba(102, 126, 234, 0.4)',
    marginBottom: '24px',
  },
  submitButtonDisabled: {
    opacity: 0.5,
    cursor: 'not-allowed',
    boxShadow: 'none',
  },
  spinner: {
    animation: 'spin 1s linear infinite',
  },
  alertError: {
    background: '#fed7d7',
    color: '#c53030',
    padding: '12px 16px',
    borderRadius: '8px',
    display: 'flex',
    alignItems: 'center',
    gap: '8px',
    marginBottom: '16px',
    fontSize: '14px',
  },
  alertSuccess: {
    background: '#c6f6d5',
    color: '#22543d',
    padding: '12px 16px',
    borderRadius: '8px',
    display: 'flex',
    alignItems: 'center',
    gap: '8px',
    marginBottom: '16px',
    fontSize: '14px',
  },
  footer: {
    marginTop: '32px',
    paddingTop: '24px',
    borderTop: '1px solid #e2e8f0',
  },
  infoBox: {
    background: '#f7fafc',
    padding: '20px',
    borderRadius: '8px',
    fontSize: '14px',
    color: '#4a5568',
  },
  infoList: {
    margin: '12px 0 0 0',
    paddingLeft: '20px',
    lineHeight: '1.8',
  },
  optionsSection: {
    background: '#f7fafc',
    padding: '24px',
    borderRadius: '12px',
    marginBottom: '20px',
    border: '1px solid #e2e8f0',
  },
  optionsTitle: {
    fontSize: '18px',
    fontWeight: '600',
    color: '#2d3748',
    margin: '0 0 20px 0',
  },
  optionGroup: {
    marginBottom: '20px',
  },
  label: {
    display: 'block',
    fontSize: '14px',
    fontWeight: '600',
    color: '#4a5568',
    marginBottom: '8px',
  },
  checkboxLabel: {
    display: 'flex',
    alignItems: 'center',
    gap: '8px',
    fontSize: '15px',
    color: '#2d3748',
    cursor: 'pointer',
  },
  checkbox: {
    width: '18px',
    height: '18px',
    cursor: 'pointer',
    accentColor: '#667eea',
  },
  radioGroup: {
    display: 'flex',
    flexDirection: 'column',
    gap: '12px',
    marginTop: '8px',
  },
  radioLabel: {
    display: 'flex',
    alignItems: 'center',
    gap: '8px',
    fontSize: '15px',
    color: '#2d3748',
    cursor: 'pointer',
  },
  radio: {
    width: '18px',
    height: '18px',
    cursor: 'pointer',
    accentColor: '#667eea',
  },
  numberInput: {
    width: '100%',
    padding: '10px 12px',
    border: '1px solid #cbd5e0',
    borderRadius: '8px',
    fontSize: '15px',
    color: '#2d3748',
    marginTop: '8px',
    transition: 'border-color 0.2s',
  },
  optionHint: {
    fontSize: '13px',
    color: '#718096',
    marginTop: '4px',
    fontStyle: 'italic',
  },
};

