<?php
class PurchaseOrderCancelService {
    public function cancel(): void {
        DB::table('purchase_orders')->update(['status' => 'cancelled']);
        PurchaseOrderCancelled::dispatch();
        ReleaseStockJob::dispatch();
    }
}
