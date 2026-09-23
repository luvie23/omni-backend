<h2>New Installation Inquiry</h2>

<p>A new quotation request has been submitted.</p>

<hr>

<p><strong>Name:</strong> {{ $quotationRequest->name }}</p>

@if($quotationRequest->company_name)
    <p><strong>Company:</strong> {{ $quotationRequest->company_name }}</p>
@endif

<p>
    <strong>Address:</strong>
    {{ $quotationRequest->address }},
    {{ $quotationRequest->city }},
    {{ $quotationRequest->state }}
    {{ $quotationRequest->zip }}
</p>

<p><strong>Phone:</strong> {{ $quotationRequest->phone_number }}</p>

<p>
    <strong>Email:</strong>
    <a href="mailto:{{ $quotationRequest->email }}">
        {{ $quotationRequest->email }}
    </a>
</p>


    <hr>

    <p><strong>Project Details:</strong></p>

    <p>{{ $quotationRequest->details }}</p>


<hr>

<p>Quotation Request ID: {{ $quotationRequest->id }}</p>
