export function Aviso({ sp }: { sp: { ok?: string; erro?: string } }) {
  if (sp.erro) return <p className="aviso erro" role="alert">{sp.erro}</p>;
  if (sp.ok) return <p className="aviso ok" role="status">{sp.ok}</p>;
  return null;
}

export type SP = Promise<{ ok?: string; erro?: string; org?: string; codigo?: string }>;
