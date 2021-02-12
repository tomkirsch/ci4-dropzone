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

Dropzone.autoDiscover = false; // so we can add the CSS class without auto-instantiation

export let dzFactory = function(target, myOptions){
	let dropzone; // create the variable before we create the options object for hositing
	let defaults = {
		url: 'uploadfile/chunk',
		postData: {}, // you can pass custom data in all the POST operations
		assemblyDone: (data, file) => console.log(data), // override this to show your image, etc.
		
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
	};
	let options = {};
	Object.assign(options, defaults);
	Object.assign(options, myOptions);
	
	let deleteChunks = (file) => {
		let chunkCount = 1;
		if(file.upload.totalChunkCount != undefined){
			chunkCount = file.upload.totalChunkCount;
		}
		let postData = options.postData;
		Object.assign(options, {
			clientName: file.name,
			dzuuid: file.upload.uuid,
			dztotalfilesize: file.size,
			dztotalchunkcount: file.upload.totalChunkCount,
		});
		$.ajax({
			method:'POST',
			url: 'uploadfile/delete',
			data: postData,
		});
	};
	options.sending = (file, xhr, formData) => {
		for(const prop in options.postData) {
			formData.append(prop, options.postData[prop]);
		}
	};
	options.chunksUploaded = (file, done) => {
		// we must save the total chunk count for error callback
		let chunkCount = file.upload.totalChunkCount;
		let postData = options.postData;
		Object.assign(postData, {
			clientName: file.name,
			dzuuid: file.upload.uuid,
			dztotalfilesize: file.size,
			dztotalchunkcount: file.upload.totalChunkCount,
		});
		// tell CI to merge all the chunks
		$.ajax({
			method:'POST',
			url: 'uploadfile/assemble',
			data: postData,
		}).fail((xhr, status, error) => {
			file.accepted = false;
			// use hoisted variable since there is no "this". not sure how to do this more elegantly...
			if(dropzone){
				// this will trigger error and chunks will be deleted via ajax
				dropzone._errorProcessing([file], 'Assembly error: '+xhr.statusText, xhr);
			}
		}).done(data => {
			done();
			file.previewElement.remove();
			options.assemblyDone(data, file);
		});
	};
	dropzone = new Dropzone(target, options);
	
	dropzone.on('error', (file, errorMessage) => {
		// CI will return an error object - use the message key to display it to the user
		if(typeof(errorMessage) === 'object'){
			dropzone._errorProcessing([file], errorMessage.message);
		}else{
			dropzone._errorProcessing([file], errorMessage);
		}
		deleteChunks(file);
	});
	return dropzone;
};

let dropzone = dzFactory($('.js-dropzone').addClass('dropzone').get(0), {
	assemblyDone: (data, file) => {
		let $el = $(`<a href="${data.filePath}" />`);
		if(data.isImage){
			$el.html(`<img src="${data.filePath}" alt="">`);
		}else{
			$el.html(data.clientName);
		}
		$('.js-uploads').append($el);
	}
});

```
