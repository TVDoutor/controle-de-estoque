# Design System (Mínimo)

Este documento define padrões visuais mínimos para manter consistência de UX/UI.

## Tipografia
- Fonte: `Inter`, fallback `system-ui`
- Escala (px): 12 / 14 / 16 / 20 / 24 / 32
- Pesos: 400 (texto), 500 (labels), 600 (títulos), 700 (KPIs)

## Espaçamento
- Escala base (px): 4 / 8 / 12 / 16 / 24 / 32 / 40
- Sempre usar múltiplos de 4

## Cores (Papéis)
- Primary: ações principais, links
- Success: confirmação, estados positivos
- Warning: manutenção/atenção
- Danger: ações destrutivas, erros
- Surface: card/painel/campo (variantes definidas em `theme-resources.php`)
- Text: heading / body / muted

## Raios e sombras
- Raios: 8 / 12 / 16 / 24
- Sombras: leve / média / forte (use `surface-card`)

## Componentes base (classes)
- Card: `surface-card`, `surface-card-tight`
- Panel: `surface-panel`
- Input/Select/Textarea: `surface-field`, `surface-select`
- Table: `surface-table-wrapper`, `surface-table-head`, `surface-table-body`
- Button (muted): `surface-button-muted`
- Icon button: `surface-icon-button`
- Divider: `surface-divider`

## Padrões de UI
- Toolbar de filtros: campo de busca + filtros + CTA primário + limpar filtros
- Alertas: success/error/info com estilo consistente
- Empty state: mensagem clara + ação sugerida

