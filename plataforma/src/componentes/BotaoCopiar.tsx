"use client";

import { useState } from "react";

export function BotaoCopiar({ texto }: { texto: string }) {
  const [copiado, setCopiado] = useState(false);
  return (
    <button
      type="button"
      className="btn sec"
      onClick={async () => {
        await navigator.clipboard.writeText(texto);
        setCopiado(true);
        setTimeout(() => setCopiado(false), 2500);
      }}
    >
      {copiado ? "Código copiado ✓" : "Copiar código Pix"}
    </button>
  );
}
