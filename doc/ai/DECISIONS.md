# DSM APPROVED DECISIONS

This file records approved product and UI decisions.

AI must not reinterpret or override these decisions without explicit user approval.

---

## Telemarketing KPI

### TM-KPI-001
KPI Telemarketing settings use **one row per indicator**.

Do not replace the primary configuration UI with popup-based KPI editing.

### TM-KPI-002
KPI Telemarketing configuration must support:

- active / inactive indicator,
- Actual source,
- KPI parameters,
- weight,
- total weight progress,
- Copy / Salin ke TM lain.

Total KPI weight must equal 100%.

### TM-KPI-003
KPI identifiers/internal IDs must not become dominant UI elements.

The interface should use human-readable indicator information.

---

## KPI Validation

### TM-VAL-001
Telemarketing KPI validation uses two conceptual panels:

1. Rincian TM lain / subordinate TM view.
2. Validation of the current TM's own KPI.

### TM-VAL-002
Primary KPI information remains compact in one row.

Relevant information includes:

- Indicator
- Target
- Actual
- Achievement
- Score when applicable
- Rincian

### TM-VAL-003
Do not add a generic **Aksi** button merely to expose KPI details.

Use **Rincian** for drill-down.

### TM-VAL-004
Rincian may show:

- counted dealers,
- dealers not yet satisfying criteria,
- reason,
- Sales owner where relevant.

---

## Target Settings

### TARGET-001
Target configuration belongs in the **Target** area/menu.

Do not move Target configuration into **System** merely for implementation convenience.

---

## Mobile

### MOBILE-001
Large tables may scroll horizontally on mobile.

Do not force all columns into unreadably narrow widths.

### MOBILE-002
Mobile UI should remain compact but readable.

Do not use excessive vertical cards when a compact layout can communicate the same information.

---

## Order Form

### ORDER-001
TM / Sales Order is non-official order data.

### ORDER-002
Item entry is manual and supports:

- Brand
- Item Name
- Color
- Qty
- Price

### ORDER-003
Order summary includes:

- Subtotal
- Credit Note
- TOTAL

TOTAL should have the strongest visual emphasis.

### ORDER-004
PDF order design direction:

- corporate
- elegant
- clean
- not overly plain
- not overly busy
- avoid excessive boxes

---

## Heatmap

### HEATMAP-001
Heatmap / presentation supports:

- PROYEKSI
- AKTUAL

toggle near Full Screen.

### HEATMAP-002
Mobile fullscreen is not required to force landscape orientation.
## Transfer Dealer

### TRANSFER-BRAND-001
Approved by user 2026-10-19.

In Transfer Dealer, the per-brand checklist shows only brands that have had ST (qty > 0) since data was published. A dealer with no ST history shows "Belum ada ST" and all its brands move together.

- If all ST brands held by the source Sales are moved and the dealer ends up fully with the destination Sales, brands without ST history move too and the Master Customer owner follows. A brand that appears later goes to the destination Sales.
- If the transfer is a split (some brand stays with the source Sales or is held by another Sales), only the selected brands move. A brand that appears later goes to the original owner (Master Customer owner).
- Preview shows which Sales will receive future new brands for each dealer.
