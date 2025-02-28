<?php

namespace App;

trait RespondTrait
{
    public function successResponse($data=null, $message = null, $code = 200)
	{
		return response()->json([
			'status'=> true, 
			'message' => $message, 
			'data' => $data
		], $code);
	}

    public function errorResponse($data=null,$error_message = null, $code = 500)
	{
		return response()->json([
			'status'=> false,
			'error' => $error_message,
			'data' => $data
		], $code);
	}
}
