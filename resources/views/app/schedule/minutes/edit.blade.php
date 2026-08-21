@extends('layouts.app')

@section('title', 'Edit MOM')
@section('page-title', 'Schedule')

@section('content')
    @include('app.schedule._tabs')
    <div class="mb-6"><p class="text-sm text-slate-500">Schedule / MOM / {{ $meetingMinute->title }}</p><h2 class="mt-1 text-2xl font-bold tracking-tight">Edit MOM</h2></div>
    @include('app.schedule.minutes._form')
@endsection
