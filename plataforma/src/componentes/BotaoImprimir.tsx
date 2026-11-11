"use client";

export function BotaoImprimir() {
  return (
    <button className="btn" onClick={() => window.print()}>
      Imprimir / salvar em PDF
    </button>
  );
}
