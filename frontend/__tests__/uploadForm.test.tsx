import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import Home from '../pages/index';

describe('Formulário de upload', () => {
  const originalEnv = process.env.NEXT_PUBLIC_BACKEND_URL;

  beforeEach(() => {
    process.env.NEXT_PUBLIC_BACKEND_URL = 'http://localhost:8000';
    window.URL.createObjectURL = jest.fn(() => 'blob:mock');
    jest.spyOn(window, 'alert').mockImplementation(() => undefined);
  });

  afterEach(() => {
    jest.restoreAllMocks();
    (global.fetch as jest.Mock | undefined)?.mockReset();
    process.env.NEXT_PUBLIC_BACKEND_URL = originalEnv;
  });

  it('envia arquivo e chama o backend', async () => {
    const mockFetch = jest.fn().mockResolvedValue({
      ok: true,
      blob: async () => new Blob(),
    });
    global.fetch = mockFetch;

    render(<Home />);

    const fileInput = screen.getByTestId('file-input') as HTMLInputElement;
    const file = new File(['nome,telefone\nAlice,5599998888'], 'contatos.xlsx', {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    });

    fireEvent.change(fileInput, { target: { files: [file] } });

    const button = screen.getByRole('button', { name: /processar lista/i });
    fireEvent.click(button);

    await waitFor(() => {
      expect(mockFetch).toHaveBeenCalledWith(
        'http://localhost:8000/api/upload',
        expect.objectContaining({
          method: 'POST',
        })
      );
    });
  });

  it('alerta quando nenhum arquivo é selecionado', () => {
    const alertSpy = jest.spyOn(window, 'alert').mockImplementation(() => undefined);

    render(<Home />);

    const button = screen.getByRole('button', { name: /processar lista/i });
    fireEvent.click(button);

    expect(alertSpy).toHaveBeenCalledWith('Selecione um arquivo');
  });
});

