DOCKER_COMPOSE_NETWORK=bunny_default
TEST_RABBITMQ_CONNECTION_URI=amqp://testuser:testpassword@rabbit_node_1:5672/testvhost

DOCKER_NETWORK_RUN=docker run --rm -i ${DOCKER_SECURITY_OPS} ${DOCKER_COMMON_NON_INTERACTIVE_OPS} ${DOCKER_COMMON_OPS} --network=${DOCKER_COMPOSE_NETWORK} -e TEST_RABBITMQ_CONNECTION_URI="${TEST_RABBITMQ_CONNECTION_URI}" "${CONTAINER_NAME}"

before-unit-tests-service: #### Start RabbitMQ for integration tests ##
	$(MAKE) -C etc/tls all
	docker compose up -d --wait
	sleep 3
	docker compose exec -T rabbit_node_1 rabbitmqctl await_startup
	docker compose exec -T rabbit_node_1 rabbitmq-diagnostics -q check_port_connectivity

after-unit-tests-service: #### Stop RabbitMQ ##
	docker compose down --remove-orphans
	sleep 3
