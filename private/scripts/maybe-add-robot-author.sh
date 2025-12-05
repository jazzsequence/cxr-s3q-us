#!/bin/bash
if git config user.email > /dev/null 2>&1; then
	git commit -m 'updating object-cache drop-in'
else
	git -c user.name='cxr robot' -c user.email='noreply@dev.null' commit -m 'updating object-cache drop-in'
fi