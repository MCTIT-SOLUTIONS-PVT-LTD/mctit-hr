

@extends('layouts.admin')

@section('page-title')
    {{ __('Manage Loan Option') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item">{{ __('Loan Option') }}</li>
@endsection

@section('action-button')
    @can('Create Allowance Option')
        <a href="#" data-url="{{ route('loanoption.create') }}" data-ajax-popup="true"
            data-title="{{ __('Create New Loan Option') }}" data-bs-toggle="tooltip" title=""
            class="btn btn-sm btn-primary" data-bs-original-title="{{ __('Create') }}">
            <i class="ti ti-plus"></i>
        </a>
    @endcan
@endsection

@section('content')
        <div class="col-3">
            @include('layouts.hrm_setup')
        </div>
        <div class="col-9">
            <div class="card">
                <div class="card-body table-border-style">

                    <div class="table-responsive">
                    <table class="table" id="pc-dt-simple">
                    <thead>
                        <tr>
                            <th>{{ __('Loan Option') }}</th>
                            <th width="200px">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($loanoptions as $loanoption)
                            <tr>
                                <td>{{ $loanoption->name }}</td>
                                <td class="Action">
                                    <div class="dt-buttons">
                                    <span>
                                        @can('Edit Loan Option')
                                            <div class="action-btn bg-info me-2">
                                                <a href="#" class="mx-3 btn btn-sm  align-items-center"
                                                    data-url="{{ URL::to('loanoption/' . $loanoption->id . '/edit')}}"
                                                    data-ajax-popup="true" data-size="md" data-bs-toggle="tooltip" title=""
                                                    data-title="{{ __('Edit Loan Option') }}"
                                                    data-bs-original-title="{{ __('Edit') }}">
                                                    <span class="text-white"><i class="ti ti-pencil"></i></span>
                                                </a>
                                            </div>
                                        @endcan

                                        @can('Delete Loan Option')
                                            <div class="action-btn bg-danger">
                                                {!! Form::open(['method' => 'DELETE', 'route' => ['loanoption.destroy', $loanoption->id], 'id' => 'delete-form-' . $loanoption->id]) !!}
                                                <a href="#" class="mx-3 btn btn-sm  align-items-center bs-pass-para"
                                                    data-bs-toggle="tooltip" title="" data-bs-original-title="Delete"
                                                    aria-label="Delete"><span class="text-white"><i
                                                        class="ti ti-trash "></i></span></a>
                                                </form>
                                            </div>
                                        @endcan
                                    </span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                    </div>
                </div>
            </div>
        </div>
@endsection


