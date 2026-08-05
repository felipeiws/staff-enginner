export const cancelPurchaseOrder = (id: string) => axios.post(`/api/purchase-orders/${id}/cancel`);
