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
	options = Object.assign(options, defaults);
	options = Object.assign(options, myOptions);
	
	let deleteChunks = (file) => {
		let chunkCount = 1;
		if(file.upload.totalChunkCount != undefined){
			chunkCount = file.upload.totalChunkCount;
		}
		const postData = Object.assign(options.postData, {
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
		const postData = Object.assign(options.postData, {
			clientName: file.name,
			dzuuid: file.upload.uuid,
			dztotalfilesize: file.size,
			dztotalchunkcount: file.upload.totalChunkCount,
		});
		for(const prop in postData) {
			formData.append(prop, postData[prop]);
		}
	};
	options.chunksUploaded = (file, done) => {
		// we must save the total chunk count for error callback
		let chunkCount = file.upload.totalChunkCount;
		const postData = Object.assign(options.postData, {
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
