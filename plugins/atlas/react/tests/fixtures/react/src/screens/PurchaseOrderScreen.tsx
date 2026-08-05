export function PurchaseOrderScreen() { return <button onClick={() => fetch('/api/purchase-orders/1/cancel', { method: 'POST' })}>Cancelar</button>; }
