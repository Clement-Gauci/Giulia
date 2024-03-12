.DEFAULT_GOAL= help
.PHONY: help unisig debug build valgrind clear app build_main build_libs

#~~~~~~~~~~~~~~~~~~~#
#     Variables     #
#~~~~~~~~~~~~~~~~~~~#

define UNISIG

$(LIGHT_RED_COLOR)
 ░▒▓██████▓▒░░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░▒▓█▓▒░      ░▒▓█▓▒░░▒▓██████▓▒░  
░▒▓█▓▒░░▒▓█▓▒░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░▒▓█▓▒░      ░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░ 
░▒▓█▓▒░      ░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░▒▓█▓▒░      ░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░ 
░▒▓█▓▒▒▓███▓▒░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░▒▓█▓▒░      ░▒▓█▓▒░▒▓████████▓▒░ 
░▒▓█▓▒░░▒▓█▓▒░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░▒▓█▓▒░      ░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░ 
░▒▓█▓▒░░▒▓█▓▒░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░▒▓█▓▒░      ░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░ 
 ░▒▓██████▓▒░░▒▓█▓▒░░▒▓██████▓▒░░▒▓████████▓▒░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░ 
$(NO_COLOR)                                       
									
$(GOLD_COLOR)░░▒▒▓▓ Clément GAUCI ▓▓▒▒░░$(NO_COLOR)
endef
export UNISIG


#~~~~~~~~~~~~~~~~~~#
#  Variables @vars #
#~~~~~~~~~~~~~~~~~~#

# vars.config
KEEPER_FILE_NAME		= .keep

# vars.cli.colors
COM_COLOR   			= \033[0;34m
OBJ_COLOR   			= \033[0;36m
OK_COLOR    			= \033[0;32m
ERROR_COLOR 			= \033[0;31m
WARN_COLOR  			= \033[0;33m
GOLD_COLOR  			= \033[1;93m
LIGHT_RED_COLOR 		= \e[91m
BACK_MAGENTA_COLOR 		= \e[45m
NO_COLOR    			= \033[m


# vars.paths.root
DIR_SEP 				= /
CURRENT_DIR 			= $(shell pwd)$(DIR_SEP)

# vars.paths.webapp
DIR_WEBAPP				= $(CURRENT_DIR)webapp$(DIR_SEP)

# vars.paths.api
DIR_API					= $(CURRENT_DIR)api$(DIR_SEP)

# vars.paths.shared
DIR_SHARED				= $(CURRENT_DIR)shared$(DIR_SEP)
DIR_SHARED_LIB			= $(DIR_SHARED)libs$(DIR_SEP)

# vars.paths.tmp
DIR_TMP					= $(CURRENT_DIR)tmp$(DIR_SEP)


#~~~~~~~~~~~~~~~~~~#
#  Common targets  #
#~~~~~~~~~~~~~~~~~~#

help: unisig ## Print helper
	@grep -E '(^[a-zA-Z_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-20s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'
	@echo "\n\n"

unisig: ## Display project's signature
	clear
	@echo "$$UNISIG"
	@echo "\n"
	@echo "Starting requested action: \n\n"

clear: unisig ## Clear tmp files
	@echo  "\n$(WARN_COLOR)Flushing tmp files...$(NO_COLOR)\n"
	@rm -rf $(DIR_TMP)/*
	@touch $(DIR_TMP)/$(KEEPER_FILE_NAME)


#~~~~~~~~~~~~~~~~~~~~~#
#    Dependencies     #
#~~~~~~~~~~~~~~~~~~~~~#


# composer.update.shared
install_shared_libs: unisig## Install shared dependencies

	@echo  "\n$(WARN_COLOR)Installing shared dependencies...$(NO_COLOR)\n"
	@cd $(DIR_SHARED_LIB) && composer update


# composer.update.all
install_libs: install_shared_libs ## Install all required dependencies