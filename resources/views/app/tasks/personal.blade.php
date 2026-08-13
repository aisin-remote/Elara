@extends('layouts.app')

@section('title', 'My tasks')
@section('page-title', 'My tasks')

@section('content')
    @include('app.tasks._database', ['showTaskTabs' => true])
@endsection
