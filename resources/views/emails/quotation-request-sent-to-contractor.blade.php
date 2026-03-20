<p>Hello {{ $contractor->name }},</p>

<p>You have received a new quotation request.</p>

<h3>Customer Details</h3>
<ul>
    <li><strong>Name:</strong> {{ $quote->name }}</li>
    <li><strong>Company / Municipality:</strong> {{ $quote->company_name }}</li>
    <li><strong>Address:</strong> {{ $quote->address }}</li>
    <li><strong>City:</strong> {{ $quote->city }}</li>
    <li><strong>State:</strong> {{ $quote->state }}</li>
    <li><strong>ZIP:</strong> {{ $quote->zip }}</li>
    <li><strong>Phone Number:</strong> {{ $quote->phone_number }}</li>
    <li><strong>Email:</strong> {{ $quote->email }}</li>
</ul>

<h3>Request Details</h3>
<p>{{ $quote->details }}</p>
