@php
    $chatgpt = App\Models\Utility::getValByName('enable_chatgpt');
@endphp

{{ Form::model($project, ['route' => ['projects.update', $project->id], 'method' => 'PUT', 'class' => 'needs-validation', 'novalidate']) }}
<div class="modal-body">

    @if ($chatgpt == 'on')
    <div class="card-footer text-end">
        <a href="#" class="btn btn-sm btn-primary" data-size="medium" data-ajax-popup-over="true"
            data-url="{{ route('generate', ['project']) }}" data-bs-toggle="tooltip" data-bs-placement="top"
            title="{{ __('Generate') }}" data-title="{{ __('Generate Content With AI') }}">
            <i class="fas fa-robot"></i> {{ __(' Generate With AI') }}
        </a>
    </div>
    @endif

    <div class="row">
        <div class="form-group col-md-12">
            {{ Form::label('name', __('Project Name'), ['class' => 'col-form-label']) }} <x-required></x-required>
            {{ Form::text('name', null, ['class' => 'form-control', 'required' => 'required', 'placeholder' => __('Enter Project Name')]) }}
        </div>

        <div class="form-group col-lg-6 col-md-6">
            {{ Form::label('employees', __('Assign Employees'), ['class' => 'col-form-label']) }}<x-required></x-required>
            {{ Form::select('employees[]', $employees, $project->employees->pluck('id')->toArray(), ['class' => 'form-control select2', 'multiple' => 'multiple', 'required' => 'required']) }}
        </div>

        
    </div>
</div>

<div class="modal-footer">
    <input type="button" value="Cancel" class="btn btn-light" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Update') }}" class="btn btn-primary">
</div>

{{ Form::close() }}

<script>
$(document).ready(function() {
    $('.select2').select2({
        placeholder: "Select employees",
        allowClear: true
    });
});
</script>