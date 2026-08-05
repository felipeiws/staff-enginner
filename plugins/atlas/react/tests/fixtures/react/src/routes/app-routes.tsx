import { Route } from 'react-router-dom';
import { PurchaseOrderScreen } from '../screens/PurchaseOrderScreen';
export const appRoutes = <Route path="/purchase-orders/:id" element={<PurchaseOrderScreen />} />;
