<?php namespace App\Controllers;

class Uploadfile extends BaseController{
	public function chunk(){
		$dropzone = service('dropzone');
		$movedFile = $dropzone->readChunk('userfile');
		return $this->response->setJSON([
			'chunk'=>$movedFile,
		]);
	}
	
	public function assemble(){
		$dropzone = service('dropzone');
		$path = 'uploads';
		if(!is_dir($path)) mkdir($path);
		$file = $this->request->getPost('filename');
		$filePath = $path.'/'.$file;
		if(file_exists($filePath)) unlink($filePath);
		$dropzone->assemble($filePath);
		return $this->response->setJSON([
			'file'=>$filePath,
		]);
	}
	
	public function clean(){
		$dropzone = service('dropzone');
		
	}
}
