<?php namespace App\Controllers;

class Uploadfile extends BaseController{
	public function chunk(){
		$dropzone = service('dropzone');
		$movedFile = $dropzone->readChunk('userfile');
		return $this->response->setJSON([
			'id'=>$dropzone->getId(),
			'chunkIndex'=>$dropzone->getChunkIndex(),
		]);
	}
	
	public function assemble(){
		$dropzone = service('dropzone');
		$path = 'uploads';
		if(!is_dir($path)) mkdir($path);
		$clientName = $this->request->getPost('clientName');
		if(!$clientName) throw new \Exception('clientName was not passed in POST');
		$filePath = $path.'/'.$clientName;
		if(file_exists($filePath)) unlink($filePath);
		$assembledFile = $dropzone->assemble($filePath);
		
		$json = [
			'id'=>$dropzone->getId(),
			'filePath'=>$filePath,
			'clientName'=>$clientName,
			'isImage'=>$dropzone->isImage(),
		];
		// if we got an image, return its width/height etc.
		if($dropzone->isImage()){
			$json = array_merge($assembledFile->getProperties(TRUE), $json);
		}
		
		return $this->response->setJSON($json);
	}
	
	public function delete(){
		$dropzone = service('dropzone');
		$numDeleted = $dropzone->delete();
		return $this->response->setJSON([
			'id'=>$dropzone->getId(),
			'numDeleted'=>$numDeleted,
		]);
	}
}
