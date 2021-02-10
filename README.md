Config/Services.php
```
	public static function dropzone(App $config = null, bool $getShared=TRUE){
		$config = $config ?? config('App');
		return $getShared ? static::getSharedInstance('dropzone', $config) : new \Tomkirsch\Dropzone\Dropzone($config);
	}
```

Your Controller:
```
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
```

Javascript:
```
import 'jquery';
import 'dropzone';

let dzOptions = {
	url: 'uploadfile/chunk',
	paramName: 'userfile',
	timeout: 180000, // needed when chunking, otherwise it'll just hang forever
	chunking: true,
	forceChunking: true,
	parallelUploads: 1,
	parallelChunkUploads: true,
	chunkSize: 1000000,  // chunk size 1,000,000 bytes (~1MB)
	retryChunks: true,
	retryChunksLimit: 3,
	addRemoveLinks: false,
	maxFilesize: null,
	acceptedFiles: null,
	chunksUploaded: (file, done) => {
		// tell CI to merge all the chunks
		$.ajax({
			method:'POST',
			url: 'uploadfile/assemble',
			data: {
				filename: file.name,
				dzuuid: file.upload.uuid,
				dztotalfilesize: file.size,
				dztotalchunkcount: file.upload.totalChunkCount,
			},
			success: (data) => { 
				done(); // must call done function
				$('.js-uploads').prepend(`<img style="max-width:200px;" src="${data.file}" alt="">`);
			},
			error: (msg) => {
				file.accepted = false;
				// tell CI to delete chunks
				$.ajax({
					method:'POST',
					url: 'uploadfile/delete',
					data: {
						filename: file.name,
						dzuuid: file.upload.uuid,
						dztotalfilesize: file.size,
						dztotalchunkcount: file.upload.totalChunkCount,
					}
				});
			}
		});
	},
};
let dropzone = new Dropzone($('.js-dropzone').get(0), dzOptions);
dropzone.on('error', (file, errorMessage) => {
	// tell CI to delete chunks
	$.ajax({
		method:'POST',
		url: 'uploadfile/delete',
		data: {
			filename: file.name,
			dzuuid: file.upload.uuid,
			dztotalfilesize: file.size,
			dztotalchunkcount: file.upload.totalChunkCount,
		},
	});
});
```
