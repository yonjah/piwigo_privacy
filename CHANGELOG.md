# Changes for v1.0.1
- Fixed Bug where changing rel_path of SrcImage affected both get_url and get_path. now replaced images will allways return original path in get_path #17

# Changes for v1.0.0
 - Feature - added config `piwigo_privacy_allow_special_chars`to allow special chars in file names
 - Fixed bug with path validation mistakenly used php input url filter #BREAKING

# Changes for v0.2.0
- Feature - added conf `piwigo_privacy_allow_whitespaces` to allow path that contain whitespaces

# Changes for v0.1.4
- Fixed bug where extensions weren't case insensitive #11

# Changes for v0.1.3
- Fixed bug where highres photos and videos would not be served #8

# Changes for v0.1.2
- Fixed bug in mime types #7
