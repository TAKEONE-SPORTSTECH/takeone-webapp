<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap');

        :root {
            --bahrain-red: #C41E3A;
            --bahrain-white: #FFFFFF;
            --text-color: #333;
            --light-gray: #F2F2F2;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f0f0f0;
            margin: 0;
            padding: 10px;
            color: var(--text-color);
        }

        .invoice-container {
            max-width: 148mm;
            width: 100%;
            margin: 0 auto;
            background-color: var(--bahrain-white);
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
            border-radius: 6px;
        }

        .header {
            background-color: var(--bahrain-red);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--bahrain-white);
            margin-bottom: 5px;
        }

        .header-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
            flex: 1;
            margin-right: 20px;
        }

        .receipt-title-wrapper {
            display: flex;
            flex-direction: column;
            gap: 5px;
            padding-bottom: 8px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.5);
            width: 100%;
        }

        .receipt-title-wrapper h1 {
            font-size: 20px;
            font-weight: 700;
            line-height: 1;
            margin: 0;
            text-transform: uppercase;
        }

        .receipt-title-wrapper .slogan {
            font-size: 13px;
            font-weight: 600;
            margin: 0;
        }

        .registration-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-top: 0;
            width: 100%;
        }

        .reg-row {
            display: flex;
            gap: 10px;
        }

        .white-box {
            background-color: var(--bahrain-white);
            color: var(--text-color);
            padding: 5px 10px;
            /* Changed border-radius to a high value for a pill shape */
            border-radius: 999px;
            font-size: 12px;
            line-height: 1.2;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        /* Fixed widths for the white boxes */
        .reg-row .white-box:first-child {
            flex: 7 1 0%;
        }

        .reg-row .white-box:last-child {
            flex: 3 1 0%;
        }

        .header img {
            max-width: 120px;
            height: auto;
        }

        .main-content {
            padding: 10px 20px 30px;
        }

        .invoice-info {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .info-details {
            text-align: left;
        }

        @media (min-width: 500px) {
            .invoice-info {
                flex-direction: row;
                justify-content: space-between;
            }
            .info-details {
                text-align: right;
            }
        }

        /* Styles for the info rows using Flexbox */
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--bahrain-red);
            border-radius: 999px;
            color: var(--bahrain-white);
            font-weight: 700;
            font-size: 14px;
            padding: 5px 15px;
            margin-left: auto;
            width: 220px; /* Adjust width to fit content */
            margin-bottom: 5px; /* Creates separation between rows */
        }

        .info-row span.white-pill {
            background-color: var(--bahrain-white);
            border-radius: 999px;
            padding: 3px 10px;
            color: var(--bahrain-red);
            font-weight: 700;
            text-align: center;
            line-height: 1;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            text-align: left;
        }

        table thead {
            background-color: var(--bahrain-red);
            color: var(--bahrain-white);
        }

        table th, table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            font-size: 12px;
        }

        table tbody tr:nth-child(even) {
            background-color: var(--light-gray);
        }

        /* Aligning text in the new 'Total' column */
        table td:last-child {
            text-align: right;
        }
        /* Aligning text in 'Price' and 'Qty' columns */
        table td:nth-child(3),
        table td:nth-child(4) {
            text-align: right;
        }

        .sub-total-table {
            width: 250px;
            float: right;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .sub-total-table td {
            padding: 8px;
            text-align: right;
            font-size: 14px;
        }

        .sub-total-table tr:not(:last-child) {
            background-color: var(--light-gray);
        }

        .sub-total-table .grand-total-row {
            background-color: var(--bahrain-red) !important;
            color: var(--bahrain-white);
            font-weight: 700;
        }

        /* New styles for the conditional note */
        .receipt-note {
            background-color: #fff3cd; /* A light, noticeable color */
            border-left: 5px solid #ffc107; /* A bright border */
            padding: 10px 15px;
            margin-top: 20px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #664d03;
            line-height: 1.4;
            display: none; /* Hide by default */
        }
        .receipt-note strong {
            color: #333;
        }

        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 15px;
            line-height: 1.4;
            font-size: 12px;
        }

        .contact-info-item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .contact-info-item .icon {
            font-size: 16px;
            color: var(--bahrain-red);
            line-height: 1;
        }

        .footer {
            background-color: var(--bahrain-red);
            padding: 10px 20px;
            text-align: center;
            color: var(--bahrain-white);
            font-size: 12px;
            font-weight: 600;
            border-bottom-left-radius: 6px;
            border-bottom-right-radius: 6px;
        }
    </style>
</head>
<body>

<div class="invoice-container">
    <div class="header">
        <div class="header-info">
            <div class="receipt-title-wrapper">
                <h1>TAKEONE</h1>
                <p class="slogan">Connect, Share, Thrive.</p>
            </div>
            <div class="registration-info">
                <div class="reg-row">
                    <div class="white-box">REGISTRATION #</div>
                    <div class="white-box">001</div>
                </div>
                <div class="reg-row">
                    <div class="white-box">VAT REGISTRATION #</div>
                    <div class="white-box">N/A</div>
                </div>
            </div>
        </div>
        <img src="{{ asset('images/logo.png') }}" alt="Company Logo">
    </div>

    <div class="main-content">
        <div class="invoice-info">
            <div>
                <p class="to"><B>RECEIPT TO :</B></p>
                <p>
                    <strong>{{ $invoice->student->full_name }}</strong><br>
                    {{ $invoice->student->mobile_formatted ?: 'N/A' }}<br>
                    {{ $invoice->student->email ?: 'N/A' }}<br>
                    {{ $invoice->student->addresses ? implode(', ', array_filter($invoice->student->addresses)) : 'N/A' }}
                </p>
            </div>
            <div class="info-details">
                <div class="info-row">
                    <span>RECEIPT NO</span>
                    <span class="white-pill">{{ $invoice->id }}</span>
                </div>
                <div class="info-row">
                    <span>ISSUE DATE</span>
                    <span class="white-pill">{{ $invoice->created_at->format('d-m-Y') }}</span>
                </div>
            </div>
        </div>

        <table id="receipt-items">
            <thead>
                <tr>
                    <th>Item No</th>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>Club Membership Fee - {{ $invoice->tenant->club_name }}</td>
                    <td>1</td>
                    <td>{{ number_format($invoice->amount, 2) }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        <div id="payment-note" class="receipt-note">
            <p><strong>Note:</strong> This payment covers the membership fee for <strong>{{ $invoice->student->full_name }}</strong> at <strong>{{ $invoice->tenant->club_name }}</strong>.</p>
        </div>

        <table class="sub-total-table">
            <tbody>
                <tr>
                    <td>Sub Total:</td>
                    <td id="sub-total"></td>
                </tr>
                <tr>
                    <td>VAT (0%):</td>
                    <td id="vat"></td>
                </tr>
                <tr class="grand-total-row">
                    <td>Grand Total:</td>
                    <td id="grand-total"></td>
                </tr>
            </tbody>
        </table>

        <div class="contact-info">
            <div class="contact-info-item">
                <span class="icon">&#9742;</span>
                <div>
                    +1 123 456 7890
                </div>
            </div>
            <div class="contact-info-item">
                <span class="icon">&#9993;</span>
                <div>
                    support@takeone.com
                </div>
            </div>
            <div class="contact-info-item">
                <span class="icon">&#9906;</span>
                <div>
                    123 Main St, City, Country
                </div>
            </div>
        </div>

    </div>
    <div class="footer">
        © 2025 TAKEONE. All Rights Reserved.
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const tableBody = document.querySelector('#receipt-items tbody');
        const subTotalElement = document.getElementById('sub-total');
        const vatElement = document.getElementById('vat');
        const grandTotalElement = document.getElementById('grand-total');
        const paymentNote = document.getElementById('payment-note');
        const vatRate = 0.0;
        let subTotal = 0;

        // --- CALCULATIONS ---
        // Loop through each table row to calculate the total for each item
        tableBody.querySelectorAll('tr').forEach(row => {
            const qty = parseFloat(row.cells[2].textContent);
            const price = parseFloat(row.cells[3].textContent);
            const total = qty * price;

            // Populate the 'Total' column
            row.cells[4].textContent = total.toFixed(2) + ' USD';

            // Add to the sub total
            subTotal += total;
        });

        const vatAmount = subTotal * vatRate;
        const grandTotal = subTotal + vatAmount;

        // Update the sub total table
        subTotalElement.textContent = subTotal.toFixed(2) + ' USD';
        vatElement.textContent = vatAmount.toFixed(2) + ' USD';
        grandTotalElement.textContent = grandTotal.toFixed(2) + ' USD';

        // --- CONDITIONAL NOTE LOGIC ---
        // Show the note
        paymentNote.style.display = 'block';
    });
</script>

</body>
</html>
