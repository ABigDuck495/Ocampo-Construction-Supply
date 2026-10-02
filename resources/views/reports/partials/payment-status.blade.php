{{-- resources/views/reports/partials/payment-status.blade.php --}}
<section id="paymentStatusPanel" class="ps-panel" 
         data-list-url="{{ route('reports.payments.data') }}"
         data-update-url-template="{{ route('reports.payments.update', ['id' => '__ID__']) }}">

    <div class="ps-toolbar">
        <input type="text" id="psSearch" class="ps-search" placeholder="SEARCH ORDER, CUSTOMER, NAME..." autocomplete="off">
        <div class="ps-filters" id="psFilters">
            <button type="button" class="ps-filter active" data-filter="all">ALL</button>
            <button type="button" class="ps-filter" data-filter="Paid">PAID</button>
            <button type="button" class="ps-filter" data-filter="Payable">PAYABLE</button>
            <button type="button" class="ps-filter" data-filter="Unpaid">UNPAID</button>
        </div>
    </div>

    <div class="ps-table-wrap">
        <table class="ps-table">
            <thead>
                <tr>
                    <th>ORDER</th>
                    <th>CUSTOMER</th>
                    <th>DATE</th>
                    <th>AMOUNT</th>
                    <th>METHOD</th>
                    <th>STATUS</th>
                    <th>RECEIVED BY</th>
                    <th>HANDED OVER BY</th>
                    <th>ACTION</th>
                </tr>
            </thead>
            <tbody id="psBody"></tbody>
        </table>
    </div>
</section>