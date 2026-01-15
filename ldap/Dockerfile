FROM osixia/openldap:1.5.0

# Copiar el archivo LDIF con los usuarios al directorio de inicio automático
COPY usuarios_iniciales.ldif /container/service/slapd/assets/config/bootstrap/ldif/custom/50-usuarios-iniciales.ldif
