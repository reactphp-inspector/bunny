ifeq ("$(IN_DOCKER)","FALSE")
DOCKER_COMMON_OPS:=${DOCKER_COMMON_OPS} --add-host=host.docker.internal:host-gateway
DOCKER_RUN_WITH_SOCKET:=docker run --rm -i ${DOCKER_SECURITY_OPS} ${DOCKER_COMMON_NON_INTERACTIVE_OPS} ${DOCKER_COMMON_OPS} ${DOCKER_SOCKET_OPS} "${CONTAINER_NAME}${DOCKER_SOCKET_CONTAINER_NAME_SUFFIX}"
endif

before-unit-tests-service: #### Start RabbitMQ for integration tests ##
	$(MAKE) -C etc/tls all
	docker compose up -d --wait
	sleep 3
	docker compose exec -T rabbit_node_1 rabbitmqctl await_startup
	docker compose exec -T rabbit_node_1 rabbitmq-diagnostics -q check_port_connectivity

after-unit-tests-service: #### Stop RabbitMQ ##
	docker compose down --remove-orphans
	sleep 3
