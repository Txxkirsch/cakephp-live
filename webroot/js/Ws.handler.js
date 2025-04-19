App.WS.addEventListener('open', function(event) {
	if (!this.subscriptions) {
		this.subscriptions = new Map();
	}
	
	this.sendJson = function(message) {
		try {
			this.send(JSON.stringify(message));
			return true;
		} catch (e) {
			console.warn('Send JSON error:', e);
			return false;
		}
	}
	
	this.publish = function(message, topic = App.NAME) {
		if (this.subscriptions.get(topic)) {
			const msg = {
				'timestamp': parseInt((new Date().getTime() / 1000).toFixed(0)),
				'type': 'publish',
				'topic': topic,
				'message': message
			};
			return this.sendJson(msg);
		}
		console.log(`You are not subscribed to ${topic}`);
		return false;
	}
	
	this.subscribe = function(topic) {
		const msg = {
			'timestamp': parseInt((new Date().getTime() / 1000).toFixed(0)),
			'type': 'subscribe',
			'topic': topic,
		};
		
		if (topic && this.sendJson(msg)) {
			this.subscriptions.set(topic, new Date());
			console.log(`Subscribed to ${topic}`);
			return true;
		}
		console.log(`Could not subscribe to ${topic}`);
		return false;
	}
	
	this.unsubscribe = function(topic) {
		if (!this.isConnected) {
			return false;
		}
		const msg = {
			'timestamp': parseInt((new Date().getTime() / 1000).toFixed(0)),
			'type': 'unsubscribe',
			'topic': topic,
		};
		
		if (topic && this.subscriptions.get(topic) && this.sendJson(msg)) {
			this.subscriptions.delete(topic);
			return true;
		}
		console.log(`Could not unsubscribe from ${topic}`);
		return false;
	}
	
	this.isConnected = function() {
		return this.readyState == 1;
	}
	
	
	if (this.subscriptions.size) {
		//re-subscribe after disconnect
		this.subscriptions.forEach((val, topic) => this.subscribe(topic));
	} else {
		//subscribe after initial loading
		this.subscribe(App.NAME);
	}
	
	$(".ws-status").addClass('text-teal').removeClass('text-danger');
});

App.WS.addEventListener('error', function(event) {
	return $(".ws-status").removeClass('text-teal').addClass('text-danger');
});

App.WS.addEventListener('close', function(event) {
	return $(".ws-status").removeClass('text-teal').addClass('text-danger');
});

App.WS.addEventListener('message', function(event) {
	try {
		event.jsonData = JSON.parse(event.data);
		if (event.jsonData.type == 'identifier') {
			this.resourceId = event.jsonData.message.resourceId;
			this.sessionId = event.jsonData.message.sessionId;
			console.log(`ConnID is ${this.resourceId}, SessionID is ${this.sessionId}`);
			return true;
		}
		if (event.jsonData.from.resourceId === null && event.jsonData.from.sessionId === this.sessionId) {
			console.log('got a message from yourself');
			// when you get a message from yourself; maybe the same window
			// return;
		}
		console.log('do something with:', event.jsonData, event.jsonData.from);
		event.jsonData.message.forEach((message) => {
			if (message.type == 'noty') {
				new Noty({...{
					callbacks: {
						onClick: function(){
							console.log(this.options);
							(undefined !== this.options.link) && window.open(this.options.link) 
						},
					}
				}, ...message.config}).show();
			} else if (message.type == 'rpc') {
				new RPC(message.uri.controller, message.uri.action, message.uri.query);
			}
			
			return message;
		});
	} catch (e) {
		console.log(`Received non-JSON message`, event.data, e);
	}
});
App.WS.open();