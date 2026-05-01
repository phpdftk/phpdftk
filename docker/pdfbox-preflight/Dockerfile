FROM eclipse-temurin:17-jre

ARG PDFBOX_VERSION=2.0.36

RUN apt-get update && apt-get install -y --no-install-recommends wget ca-certificates \
    && mkdir -p /opt/lib \
    && wget -q "https://dlcdn.apache.org/pdfbox/${PDFBOX_VERSION}/preflight-app-${PDFBOX_VERSION}.jar" \
       -O /opt/preflight-app.jar \
    && wget -q "https://repo1.maven.org/maven2/javax/activation/activation/1.1.1/activation-1.1.1.jar" \
       -O /opt/lib/activation.jar \
    && wget -q "https://repo1.maven.org/maven2/javax/xml/bind/jaxb-api/2.3.1/jaxb-api-2.3.1.jar" \
       -O /opt/lib/jaxb-api.jar \
    && wget -q "https://repo1.maven.org/maven2/org/glassfish/jaxb/jaxb-runtime/2.3.9/jaxb-runtime-2.3.9.jar" \
       -O /opt/lib/jaxb-runtime.jar \
    && wget -q "https://repo1.maven.org/maven2/com/sun/istack/istack-commons-runtime/3.0.12/istack-commons-runtime-3.0.12.jar" \
       -O /opt/lib/istack.jar \
    && apt-get purge -y wget && apt-get autoremove -y && rm -rf /var/lib/apt/lists/*

ENTRYPOINT ["java", "-cp", "/opt/preflight-app.jar:/opt/lib/*", "org.apache.pdfbox.preflight.Validator_A1b"]
