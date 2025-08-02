functional-testing: ## Run functional tests ##*A*##
ifeq ("$(wildcard test/tls/ca.pem)","")
	make -C etc/tls all
endif
	docker compose up -d
	sleep 6 # Find a good wait-for solution
	($(MAKE) unit-testing) || docker compose down
	docker compose down
