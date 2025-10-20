CustomCertElement TextReplace
CustomCertElement TextReplace é um plugin para o Moodle que permite realizar substituições dinâmicas de texto em certificados personalizados, utilizando placeholders com valores automáticos ou personalizados. Ideal para instituições que necessitam ajustar o conteúdo dos certificados de forma flexível e automatizada.

📝 Descrição
Este plugin é uma extensão do Custom Certificate do Moodle, adicionando um novo elemento que permite substituir variáveis ou textos padrões por novos valores no momento da geração do certificado. Assim, administradores e professores podem criar certificados ainda mais personalizados para seus cursos.

🚀 Funcionalidades
Substituição automática de textos ou placeholders definidos.

Uso de variáveis padrão e campos personalizados do usuário e do curso.

Suporte à criação de novas variáveis personalizadas.

Integração nativa com o plugin Custom Certificate.

Configuração simples diretamente pela interface de administração do certificado.

Compatível com múltiplas versões do Moodle.

🧩 Uso de Variáveis
O plugin permite o uso de variáveis no formato {contexto:identificador}, que são substituídas automaticamente pelo valor correspondente no momento da geração do certificado.

Exemplos:
Usuário:

{user:lastname} → sobrenome do usuário.

{user:identificador_campo_personalizado} → valor de um campo de perfil personalizado do usuário.

Curso:

{course:fullname} → nome completo do curso.

{course:identificador_campo_personalizado} → valor de um campo personalizado do curso.

Esse recurso oferece grande flexibilidade para a personalização de certificados, adaptando o conteúdo conforme as informações específicas de cada usuário e curso.

🔧 Instalação
Clone ou baixe o repositório:

bash
Copiar
Editar
git clone https://github.com/vandersonfarias/customcertelement_textreplace.git
Copie a pasta do plugin para o diretório de elementos do Custom Certificate no Moodle:

swift
Copiar
Editar
[moodle]/mod/customcert/element/
Acesse a interface de administração do Moodle e siga as instruções para concluir a instalação do plugin.

Adicione o elemento Text Replace ao seu certificado personalizado e configure conforme necessário.

⚙️ Configuração
Ao editar ou criar um certificado, adicione o elemento Text Replace.

Defina os textos ou placeholders a serem substituídos e os valores correspondentes.

Salve o certificado e visualize a substituição aplicada no certificado gerado.

✅ Requisitos
Moodle com o plugin Custom Certificate instalado e configurado.

Acesso de administrador ou permissões adequadas para instalação de plugins.

📄 Licença
Este projeto está licenciado sob a GNU GPL v3.

🙋‍♂️ Contribuições
Contribuições são bem-vindas! Sinta-se à vontade para abrir issues ou enviar pull requests com melhorias, correções ou sugestões.

📫 Contato
Desenvolvido por Vanderson Farias.
Em caso de dúvidas ou sugestões, entre em contato através do GitHub.

