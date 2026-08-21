@extends('layouts.requester')

@section('title', 'Edit '.$meetingMinute->title)
@section('page-title', 'MOM')

@section('content')
    <div class="mb-6"><p class="text-sm text-slate-500">Schedule / MOM</p><h2 class="mt-1 text-2xl font-bold tracking-tight">Edit MOM</h2></div>
    @include('app.schedule.minutes._form')
@endsection
