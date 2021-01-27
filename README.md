# Installation
[Download Dropzone](https://www.dropzonejs.com/) or use the modified file in `dropzonejs-modified` folder. A simple edit was made at line 2336.

Create the service in `App\Config\Services.php`:
```
	public static function dropzone(App $config = null, $getShared = true){
		if (! is_object($config)) $config = config(App::class);
		return $getShared ? static::getSharedInstance('dropzone', $config) : new \Tomkirsch\Dropzone\Dropzone($config);
	}
```

Use it in your controller:
```
class Upload_file extends Controller{
	public function dropzone(){
		$dropzone = service('dropzone');
		$isComplete = $dropzone->readChunk($this->request, 'userfile', 'path/to/uploads', 'myNewFileName', TRUE);
		if($isComplete){
			$file = $dropzone->getFinalFile();
			$origFileName = $dropzone->getClientName();
			// resize, store in DB, etc.
		}
		return $this->response->setJSON(['complete'=>$isComplete]);
	}
}
```

Write your JS. As of 2019, the dropzone code is a little buggy so it needs a little help. This code needs to use the `dropzonejs-modified` script.
```
var MyDropzone = function(selector, options){
	var that = this;
	this.selector = selector;
	this.options = $.extend({maxFiles: Infinity}, options);
	this.options = $.extend({
		url: 'https://website.com/upload-file/dropzone',
		timeout: 180000, // needed when chunking, otherwise it'll just hang forever
		chunking: true,
		forceChunking: true,
		parallelUploads: 1,
		parallelChunkUploads: true,
		chunkSize: 1000000,  // chunk size 1,000,000 bytes (~1MB)
		retryChunks: true,
		retryChunksLimit: 3,
		paramName: 'userfile',
		addRemoveLinks: false,
		maxFilesize: null,
		acceptedFiles: null,
		addedfile: function(file, dropzone){
			// noop
			// if(this.files.length > that.options.maxFiles) this.removeFile(this.files[0]); // allow 1 file at a time
		},
		sending: function(file, xhr, formData, dropzone){
			// noop
		},
		done: function(file, response, dropzone){
			// noop
		},
		chunksUploaded: function(file, done){
			// this is called when all chunks are uploaded. The issue is that we don't know which response is the "final" one, so we need to loop thru the chunks and find it.
			var finalResponse = null;
			$.each(file.upload.chunks, function(index, chunk){
				// NOTE: Dropzone removes each chunk's XHR to prevent memory leaks
				// Dropzone code has been modified so that the xhr object is simply replaced with a generic object containing the response.

				// need to parse the response as JSON
				var response = that.parseResponse(chunk.xhr.response);
				if(typeof(response) !== 'object'){
					finalResponse = response;
				}else if(!finalResponse && response.complete === true){
					finalResponse = response;
				}
			});
			done(); // call DZ's done function
			that.success(file, finalResponse);
		},
	}, this.options);
	this.dropzone = new Dropzone(selector, this.options);
};
MyDropzone.prototype.success = function(file, response){
	if(typeof(response) !== 'object'){
		alert('There was a problem adding ' + file.name + '. ');
		console.log(response);
	}else if(response.complete){
		this.options.done(file, response);
	}else{
		alert('dropzone called success on in-progress response; please try uploading again.');
	}
};
MyDropzone.prototype.parseResponse = function(response){
	var json;
	try{
		json = JSON.parse(response);
	}catch(e){
		json = response;
	}
	return json;
};
```
Use your dropzone on the page:
```
$(function(){
	var dropzone = new MyDropzone(selector, {
		addedfile: function(file){
			// allow only 1 file at a time
			if(this.files.length > 1) this.removeFile(this.files[0]);
		},
		sending: function(file, xhr, formData){
			// send data from the form in POST
			var formItems = $('form').serializeArray();
			$(formItems).each(function(i,data){
				formData.append(data.name, data.value);
			});
		},
		done: function(file, response){
			// do something
		},
	});
});