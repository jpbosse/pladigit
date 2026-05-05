@extends('layouts.app')
@section('title', $table->label)

@section('content')
    @livewire('tenant.datagrid.show-grid', ['table' => $table])
@endsection
