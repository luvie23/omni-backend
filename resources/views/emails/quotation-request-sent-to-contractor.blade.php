<p>Hello {{ $contractor->company_name }},</p>

<p>
    You’ve received a new customer inquiry through <strong>OMNI RGB</strong>.
    This customer is looking for help with an OMNI RGB lighting project and has been referred to you as an OMNI RGB contractor in their area.
</p>

<p>
    Please review their information below and reach out to them directly to discuss their project and provide a quote.
</p>

<h3>Customer Information</h3>

<ul>
    <li><strong>Name:</strong> {{ $quote->name }}</li>

@if($quote->company_name)
    <li><strong>Company / Municipality:</strong> {{ $quote->company_name }}</li>
@endif

<li>
    <strong>Location:</strong>
    {{ $quote->address }}, {{ $quote->city }}, {{ $quote->state }} {{ $quote->zip }}
</li>

<li>
    <strong>Phone:</strong>
    <a href="tel:{{ $quote->phone_number }}">{{ $quote->phone_number }}</a>
</li>

<li>
    <strong>Email:</strong>
    <a href="mailto:{{ $quote->email }}">{{ $quote->email }}</a>
</li>

</ul>

<h3>Project Details</h3>

@if($quote->details)
<p>{{ $quote->details }}</p>
@else
<p>No additional project details were provided.</p>
@endif

<p>
    We recommend contacting the customer as soon as possible while their inquiry is still fresh.
</p>

<p>
    <strong>Do not reply to this email.</strong><br>
    If you have any questions or need assistance, please email
    <a href="mailto:cs@lightsfordecorators.com">cs@lightsfordecorators.com</a>.
</p>

<p>
    Thank you,<br>
    <strong>OMNI RGB</strong>
</p>
