@extends('layouts.requester')

@section('title', 'New MOM')
@section('page-title', 'Schedule')

@section('content')
    <div class="mb-6"><p class="text-sm text-slate-500">Schedule / {{ $scheduleEvent->title }}</p><h2 class="mt-1 text-2xl font-bold tracking-tight">Create MOM</h2></div>
    @include('app.schedule.minutes._form')
@endsection
