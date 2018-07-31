/*
FastSSE v0.1.1

Creative GP
2018/07/29 (yyyy/mm/dd)
*/
'use strict';

class Stream {
	constructor(sender, receiver) {
		this.es = new EventSource(sender);

		this.receiver = receiver;

		this.Base64 = {
			encode: function (str) {
				return btoa(unescape(encodeURIComponent(str)));
			},
			decode: function (str) {
				return decodeURIComponent(escape(atob(str)));
			}
		};
	}

	send(data, tags) {
		//				const tags = tags.join(',');
		fetch(this.receiver + `?q=${this.Base64.encode(data)}&t=${tags}`);
	}

	set OnMessage(func) {
		// Decode newlines and call `func`
		this.onMessage = e => func({...e, fsdata: e.data.replace(/%\\n/g, "\n")});
		this.es.addEventListener('message', func);
	}

	set OnError(func) {
		this.onError = func;
		this.es.onerror = func;
	}
}
