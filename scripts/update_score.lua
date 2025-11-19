-- scripts/update_score.lua
local key = KEYS[1]
local member = KEYS[2]
local score = tonumber(ARGV[1])
local ts = tonumber(ARGV[2])
local tie_breaker = 9999999999999 - ts
local composite = score + tie_breaker / 10000000000000

redis.call('ZADD', key, composite, member)

return redis.call('ZREVRANK', key, member) + 1