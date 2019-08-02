
@extends('master')

@section('title')
    Scrape Pinterest
@stop
@section('content')
    <div class="row">
        <a class="pull-right btn" href="{{url('/')}}"><i class="fa fa-home"></i> Home Page</a>
    </div>
    <div class="col-sm-12 col-lg-8 col-lg-offset-2">

        <h2 class="text-center">Pinterest</h2>
        <form-pinterest></form-pinterest>
    </div>
@stop